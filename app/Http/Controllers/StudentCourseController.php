<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Meus Cursos": an Aluno's own enrollments across every
 * Organization they belong to. Restricted to `role:aluno` (see
 * `routes/web.php`) — not `student.enrolled`, since this listing IS the
 * enrollment data itself, with no single `{course}`/`{lesson}` route
 * parameter to gate. Reads across every Organization the student is
 * enrolled in, intentionally bypassing `Course`'s `org` global scope (a
 * student may hold enrollments in Courses from more than one
 * Organization) — `withoutGlobalScope('org')` is required here since an
 * Aluno carries no `org_id` of their own, which would otherwise make
 * `Course`'s `OrgScope` filter every enrollment out (mirrors
 * `User::hasActiveOrCompletedEnrollment()`'s same convention).
 *
 * Only `org` is bypassed, never the whole global-scope set: a
 * soft-deleted Course must stay excluded (its `course_user` row is not
 * cascade-removed by a soft delete), so `SoftDeletes`' own global scope
 * is left in place. Only `active`/`completed` pivot statuses are listed
 * — a `cancelled` enrollment (e.g. via `EnrollmentController::destroy()`)
 * is excluded so this page never shows a course
 * `EnsureStudentIsEnrolled` would 403 on.
 *
 * The three status tabs map directly onto the `course_user.status`
 * value, not the derived display status: "Em andamento" is every
 * `active` enrollment (whether its derived chip reads `nao_iniciado`,
 * `em_andamento`, or `expirado` — `expirado` is a read of an `active`
 * pivot past its `expires_at`, not a 4th pivot status), "Concluídos" is
 * every `completed` enrollment, and "Todos" is simply both (this
 * listing never includes `cancelled` rows in the first place).
 */
class StudentCourseController extends Controller
{
    private const TABS = ['em_andamento', 'concluidos', 'todos'];

    public function index(Request $request): View
    {
        $requestedTab = $request->string('status')->toString();
        $activeTab = in_array($requestedTab, self::TABS, true) ? $requestedTab : 'em_andamento';

        /** @var User $user */
        $user = Auth::user();

        $enrollments = $user->courses()
            ->withoutGlobalScope('org')
            ->wherePivotIn('status', ['active', 'completed'])
            ->with([
                'organization',
                'modules' => fn ($query) => $query->orderBy('order_index'),
                'modules.lessons' => fn ($query) => $query->where('is_published', true)->orderBy('order_index'),
                'modules.lessons.progress' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->get();

        $certificatesByCourseId = Certificate::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $enrollments->pluck('id'))
            ->whereNull('revoked_at')
            ->orderByDesc('issued_at')
            ->get()
            ->groupBy('course_id');

        $rows = $enrollments->map(
            fn (Course $course): object => $this->buildRow($course, $certificatesByCourseId->get($course->id, collect()))
        );

        $tabRows = [
            'em_andamento' => $rows->filter(fn (object $row): bool => $row->pivotStatus === 'active')->values(),
            'concluidos' => $rows->filter(fn (object $row): bool => $row->pivotStatus === 'completed')->values(),
        ];
        $tabRows['todos'] = $rows->values();

        $tabCounts = [
            'em_andamento' => $tabRows['em_andamento']->count(),
            'concluidos' => $tabRows['concluidos']->count(),
            'todos' => $tabRows['todos']->count(),
        ];

        return view('student.courses.index', [
            'activeTab' => $activeTab,
            'rows' => $tabRows[$activeTab],
            'tabCounts' => $tabCounts,
        ]);
    }

    /**
     * Builds the lightweight per-card view model for one enrollment: the
     * derived status chip, the visually-clamped progress percentage, the
     * resolved CTA target/label for that status, the optional secondary
     * CTA slot (a `concluido` row's certificate link, or its
     * "Certificado em emissão" placeholder), and the metadata a card
     * footer needs ("{N} aulas · {N}h · Prazo: DD/MM/AAAA").
     */
    private function buildRow(Course $course, Collection $certificates): object
    {
        $pivot = $course->pivot;
        $displayStatus = $course->enrollmentDisplayStatusFor($pivot);

        $publishedLessons = $this->publishedLessonsInOrder($course);
        $lessonsCount = $publishedLessons->count();

        [$ctaLabel, $ctaHref] = $this->resolveCta($course, $displayStatus, $publishedLessons);
        [$secondaryCtaLabel, $secondaryCtaHref] = $displayStatus === 'concluido'
            ? $this->certificateCta($certificates)
            : [null, null];

        return (object) [
            'course' => $course,
            'organization' => $course->organization,
            'pivotStatus' => $pivot->status,
            'displayStatus' => $displayStatus,
            'progressPercentage' => $this->visualProgressPercentage((int) ($pivot->progress_percentage ?? 0), $displayStatus),
            'ctaLabel' => $ctaLabel,
            'ctaHref' => $ctaHref,
            'secondaryCtaLabel' => $secondaryCtaLabel,
            'secondaryCtaHref' => $secondaryCtaHref,
            'lessonsCount' => $lessonsCount,
            'workloadHours' => (int) $course->workload_hours,
            'deadlineLabel' => $this->resolveDeadlineLabel($pivot, $displayStatus),
        ];
    }

    /**
     * Applies the design system's "never look like 0%/a bug" rule: any
     * enrollment that is not `nao_iniciado` shows at least a 2% bar, even
     * when real progress is 0 (e.g. an `expirado` enrollment the student
     * never started). A genuinely `nao_iniciado` enrollment still renders
     * a true 0% bar.
     */
    private function visualProgressPercentage(int $rawPercentage, string $displayStatus): int
    {
        if ($rawPercentage > 0) {
            return max($rawPercentage, 2);
        }

        return $displayStatus === 'nao_iniciado' ? 0 : 2;
    }

    /**
     * @param  Collection<int, Lesson>  $publishedLessons
     * @return array{0: ?string, 1: ?string} [label, href] — both `null`
     *                                       when no CTA can be resolved (e.g. a course with no published
     *                                       Lessons yet). Callers must degrade the button rather than link
     *                                       to a 404. A `concluido` row ALWAYS resolves the classroom — the
     *                                       enrollment gate (`EnsureStudentIsEnrolled`) and the forum
     *                                       policies admit a `completed` pivot, so a finished student must
     *                                       keep a clickable path back into the content; the certificate
     *                                       travels on the row's secondary CTA slot instead.
     */
    private function resolveCta(Course $course, string $displayStatus, Collection $publishedLessons): array
    {
        return match ($displayStatus) {
            'nao_iniciado' => $this->lessonCta($publishedLessons->first(), 'Começar curso'),
            'em_andamento' => $this->lessonCta($this->resumeLessonFor($publishedLessons), 'Continuar'),
            'concluido' => ['Ver sala de aula', route('classroom.show', $course)],
            'expirado' => ['Ver o que você fez', route('classroom.show', $course)],
            default => [null, null],
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function lessonCta(?Lesson $lesson, string $label): array
    {
        if (! $lesson) {
            return [null, null];
        }

        return [$label, route('classroom.lesson', $lesson)];
    }

    /**
     * The secondary CTA slot of a `concluido` row: "Baixar certificado"
     * when a non-revoked Certificate was already issued, or the neutral
     * "Certificado em emissão" placeholder (no link — the button/link
     * must degrade rather than 404) while it hasn't. The primary CTA is
     * the classroom either way.
     *
     * @param  Collection<int, Certificate>  $certificates  this student's non-revoked
     *                                                      Certificates for the Course, newest `issued_at` first (see
     *                                                      the `index()` batch query) — never a fresh DB round-trip
     *                                                      per row.
     * @return array{0: ?string, 1: ?string}
     */
    private function certificateCta(Collection $certificates): array
    {
        $certificate = $certificates->first();

        if (! $certificate) {
            return ['Certificado em emissão', null];
        }

        return ['Baixar certificado', route('certificates.download', $certificate)];
    }

    /**
     * In-memory equivalent of `Course::firstPublishedLessonFor()`/
     * `resumeLessonFor()`'s ordering, built from the already
     * eager-loaded `modules`/`modules.lessons` relations (see
     * `index()`'s ordered `with()`) instead of the raw-join query those
     * Model methods run — avoids a DB round-trip per enrollment row.
     *
     * @return Collection<int, Lesson>
     */
    private function publishedLessonsInOrder(Course $course): Collection
    {
        return $course->modules->flatMap(fn ($module) => $module->lessons)->values();
    }

    /**
     * In-memory equivalent of `Course::resumeLessonFor()` that reads each
     * Lesson's already eager-loaded, already user-scoped `progress`
     * relation (see `index()`'s `modules.lessons.progress` eager load)
     * instead of issuing its own `LessonProgress` queries — delegates the
     * actual ordering/tie-break algorithm to
     * `Course::resolveResumeLesson()` so both call sites share exactly
     * one implementation of the CTA-resolution rule.
     *
     * @param  Collection<int, Lesson>  $publishedLessons
     */
    private function resumeLessonFor(Collection $publishedLessons): ?Lesson
    {
        if ($publishedLessons->isEmpty()) {
            return null;
        }

        $progressRecords = $publishedLessons->flatMap(fn (Lesson $lesson) => $lesson->progress);

        return Course::resolveResumeLesson($publishedLessons, $progressRecords);
    }

    private function resolveDeadlineLabel(object $pivot, string $displayStatus): ?string
    {
        if ($displayStatus === 'concluido' && $pivot->completed_at) {
            return Carbon::parse($pivot->completed_at)->format('d/m/Y');
        }

        if ($pivot->expires_at) {
            return Carbon::parse($pivot->expires_at)->format('d/m/Y');
        }

        return null;
    }
}
