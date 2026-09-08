<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Notifications\CertificateIssuedNotification;
use App\Services\AuditService;
use App\Services\CourseCompletionEvaluator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * the certificate-eligibility engine, called synchronously
 * by `IssueCertificateOnCourseCompletion` (`QUEUE_CONNECTION=sync`) right
 * after `CourseCompletedByStudent` fires. Evaluates **every**
 * `course_completion_rules` row of the Course — all 3 `rule_type`s must
 * pass (AND) — and is a no-op when the Course has no rules defined at all
 * (an unattainable certificate is preferable to a wrongly-issued one).
 *
 * Rule evaluation itself lives in `CourseCompletionEvaluator`, shared
 * with `EvaluateCourseCompletionAction` so issuance and enrollment
 * completion can never disagree about what "concluded" means.
 *
 * `validation_hash = sha256(user_id.course_id.formatted_issued_at.APP_KEY)`
 * per . `formatted_issued_at` uses the fixed
 * `Y-m-d H:i:s` Carbon format — this exact format must never be
 * re-derived differently elsewhere (see the `certificates-conventions`
 * skill).
 *
 * Idempotent via `certificates`' `UNIQUE(user_id, course_id)`: an existing
 * row for the pair — revoked or not — is returned as-is and never
 * duplicated/re-issued/un-revoked. A `QueryException` from a
 * `firstOrCreate` race (two concurrent calls for the same pair) is caught
 * and resolved to the row the other call just inserted, rather than
 * bubbling up.
 */
class IssueCertificateAction
{
    public function __construct(
        protected CourseCompletionEvaluator $evaluator = new CourseCompletionEvaluator,
    ) {}

    public function execute(Course $course, User $user): ?Certificate
    {
        if (! $this->evaluator->satisfied($course, $user)) {
            return null;
        }

        $issuedAt = now();
        $formattedIssuedAt = $issuedAt->format('Y-m-d H:i:s');
        $validationHash = hash('sha256', $user->id.$course->id.$formattedIssuedAt.config('app.key'));

        try {
            $certificate = Certificate::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['validation_hash' => $validationHash, 'issued_at' => $issuedAt],
            );
        } catch (QueryException) {
            return Certificate::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
        }

        // only a genuine insert fires the
        // notification; `firstOrCreate`'s idempotent re-fetch of an
        // already-existing row (e.g. a later progress recalculation for a
        // student who was already issued a certificate) must never
        // re-notify. 's business transaction,
        // so the send is wrapped in try/catch and logged rather than left
        // to propagate.
        if ($certificate->wasRecentlyCreated) {
            try {
                $user->notify(new CertificateIssuedNotification($certificate));
            } catch (Throwable $exception) {
                Log::error('Falha ao enviar notificação de certificado emitido.', [
                    'certificate_id' => $certificate->id,
                    'exception' => $exception->getMessage(),
                ]);
            }

            // only a genuine issuance is audited, mirroring
            // the notification's own `wasRecentlyCreated` guard above.
            try {
                AuditService::log(
                    event: 'certificate.issued',
                    orgId: $course->org_id ? (int) $course->org_id : null,
                    userId: $user->id,
                    payload: [
                        'certificate_id' => $certificate->id,
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'validation_hash' => $certificate->validation_hash,
                    ],
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $certificate;
    }
}
