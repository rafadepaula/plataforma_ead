<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\CertificateIssuedNotification;
use App\Services\AuditService;
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
 * `validation_hash = sha256(user_id.course_id.formatted_issued_at.APP_KEY)`
 * per RN01/RN07. `formatted_issued_at` uses the fixed
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
    public function execute(Course $course, User $user): ?Certificate
    {
        $rules = $course->completionRules()->get();

        if ($rules->isEmpty()) {
            return null;
        }

        foreach ($rules as $rule) {
            if (! $this->ruleSatisfied($rule, $course, $user)) {
                return null;
            }
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

    private function ruleSatisfied(CourseCompletionRule $rule, Course $course, User $user): bool
    {
        return match ($rule->rule_type) {
            'all_lessons' => $this->allLessonsSatisfied($rule, $course, $user),
            'min_quiz_score' => $this->minQuizScoreSatisfied($rule, $user),
            'specific_module' => $this->specificModuleSatisfied($rule, $user),
            default => false,
        };
    }

    /**
     * `course_user.progress_percentage >= required_percentage` for this
     * student. Missing/no enrollment pivot is treated as 0%.
     */
    private function allLessonsSatisfied(CourseCompletionRule $rule, Course $course, User $user): bool
    {
        $enrollment = $course->students()->where('users.id', $user->id)->first();

        $progress = $enrollment?->pivot?->progress_percentage ?? 0;

        return $progress >= $rule->required_percentage;
    }

    /**
     * `target_id` points to `quizzes.id`; a `target_id` that no longer
     * resolves (deleted quiz) is treated as not-satisfied rather than
     * throwing.
     */
    private function minQuizScoreSatisfied(CourseCompletionRule $rule, User $user): bool
    {
        $quiz = Quiz::find($rule->target_id);

        if (! $quiz) {
            return false;
        }

        $bestScore = $user->bestQuizScoreFor($quiz);

        return $bestScore !== null && $bestScore >= $rule->required_percentage;
    }

    /**
     * `target_id` points to `modules.id`; a `target_id` that no longer
     * resolves (deleted module) is treated as not-satisfied rather than
     * throwing. Every Lesson of the target Module must have
     * `lesson_progress.is_completed = true` for this student.
     */
    private function specificModuleSatisfied(CourseCompletionRule $rule, User $user): bool
    {
        $module = Module::find($rule->target_id);

        if (! $module) {
            return false;
        }

        $lessonIds = $module->lessons()->pluck('id');

        if ($lessonIds->isEmpty()) {
            return false;
        }

        $completedCount = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('is_completed', true)
            ->count();

        return $completedCount === $lessonIds->count();
    }
}
