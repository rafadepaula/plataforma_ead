<?php

namespace App\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes the Admin/Gestor dashboard's stat cards and
 * "Matrículas recentes" table.
 *
 * `Certificate` and `course_user` (the `courses`↔`users` pivot) carry no
 * `OrgScope` of their own (cascade-inherited tenancy through `Course` —
 * see the `tenancy-architecture` skill), so every method here receives an
 * explicit, already-resolved `$orgId` (`null` meaning "no filter", i.e. an
 * Admin with no active Impersonate Org context) and joins through
 * `courses.org_id` by hand — it never reads `Auth::user()`/
 * `session('active_org_id')` itself. `courses_count` is the one exception:
 * `Course` already carries `OrgScope`, so `Course::query()->count()` is
 * left to that trait's own resolution rather than hand-rolled here, per
 * the callers passing the same `$orgId` they resolved for the rest of the
 * stats.
 */
class DashboardMetricsService
{
    private const DEFAULT_RECENT_ENROLLMENTS_LIMIT = 10;

    private const DEFAULT_MOST_COMPLETED_COURSES_LIMIT = 5;

    /**
     * Certificates issued within this many days of "now" still count as
     * "prontos" in `attentionCounts()` — a short, informational recency
     * window (not a pendency, unlike the other two counts), matching the
     * dashboard's "pendência é trabalho a fazer, nunca urgência" tone.
     */
    private const CERTIFICATES_READY_WINDOW_DAYS = 7;

    /**
     * Computes the stat cards' current values plus a real, period-over-
     * period delta for `active_students`/`certificates_issued` (the two
     * cards the view renders with a delta chip). `$period` only controls
     * the length of the comparison window — `getStats()` itself still
     * returns the all-time current values, exactly as before.
     *
     * @return array{active_students: int, certificates_issued: int, completion_rate: int, courses_count: int, active_students_delta: ?string, certificates_issued_delta: ?string}
     */
    public function getStats(?int $orgId, string $period = '30d'): array
    {
        $periodStart = $this->periodStart($period);

        $activeStudents = $this->activeStudentsCount($orgId);
        $certificatesIssued = $this->certificatesIssuedCount($orgId);

        $previousActiveStudents = $this->activeStudentsCount($orgId, $periodStart);
        $previousCertificatesIssued = $this->certificatesIssuedCount($orgId, $periodStart);

        return [
            'active_students' => $activeStudents,
            'certificates_issued' => $certificatesIssued,
            'completion_rate' => $this->completionRate($orgId),
            'courses_count' => Course::query()->count(),
            'active_students_delta' => $this->percentDelta($activeStudents, $previousActiveStudents),
            'certificates_issued_delta' => $this->percentDelta($certificatesIssued, $previousCertificatesIssued),
        ];
    }

    /**
     * "Precisa da sua atenção": counts of open work items for the acting
     * scope, never a blocking state — see the dashboard view's tone note.
     *
     * - `pending_essays`: `QuizAttempt`s awaiting manual grading, joined
     *   through `quiz.lesson.module.course.org_id` (none of those tables
     *   carry `OrgScope` — cascade-inherited tenancy, see
     *   `tenancy-architecture`).
     * - `forum_reports`: pending `ForumReport`s. `postable_type`/
     *   `postable_id` are a pseudo-polymorphic pair with no DB FK (see
     *   `ForumReport::postable()`), so this unions the two concrete
     *   postable tables by hand rather than resolving each row in PHP.
     * - `certificates_ready`: certificates issued in the last
     *   {@see self::CERTIFICATES_READY_WINDOW_DAYS} days — informational,
     *   not a pendency.
     *
     * @return array{pending_essays: int, forum_reports: int, certificates_ready: int}
     */
    public function attentionCounts(?int $orgId): array
    {
        return [
            'pending_essays' => $this->pendingEssaysCount($orgId),
            'forum_reports' => $this->pendingForumReportsCount($orgId),
            'certificates_ready' => $this->certificatesIssuedSince($orgId, $this->daysAgo(self::CERTIFICATES_READY_WINDOW_DAYS)),
        ];
    }

    /**
     * "Cursos mais concluídos": top Courses by completed enrollments,
     * with a 0–100 `percentage` (relative to the top row) ready for the
     * mint bar chart — no CSS/JS computes that ratio, it is data.
     *
     * @return Collection<int, object{course_name: string, completions: int, percentage: int}>
     */
    public function mostCompletedCourses(?int $orgId, int $limit = self::DEFAULT_MOST_COMPLETED_COURSES_LIMIT): Collection
    {
        $rows = DB::table('course_user')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->where('course_user.status', 'completed')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('courses.title')
            ->limit($limit)
            ->get(['courses.title as course_name', DB::raw('count(*) as completions')]);

        $max = (int) ($rows->max('completions') ?? 0);

        return $rows->map(fn ($row) => (object) [
            'course_name' => $row->course_name,
            'completions' => (int) $row->completions,
            'percentage' => $max > 0 ? (int) round(((int) $row->completions / $max) * 100) : 0,
        ]);
    }

    /**
     * @return Collection<int, object{student_name: string, student_email: string, course_name: string, progress_percentage: int, status_label: string, status_badge_variant: string}>
     */
    public function recentEnrollments(?int $orgId, int $limit = self::DEFAULT_RECENT_ENROLLMENTS_LIMIT): Collection
    {
        return User::query()
            ->join('course_user', 'course_user.user_id', '=', 'users.id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->orderByDesc('course_user.created_at')
            ->limit($limit)
            ->get([
                'users.name as student_name',
                'users.email as student_email',
                'courses.title as course_name',
                'course_user.status as status',
                'course_user.progress_percentage as progress_percentage',
            ])
            ->map(fn ($row) => (object) [
                'student_name' => $row->student_name,
                'student_email' => $row->student_email,
                'course_name' => $row->course_name,
                'progress_percentage' => (int) $row->progress_percentage,
                'status_label' => $this->statusLabel($row->status),
                'status_badge_variant' => $this->statusBadgeVariant($row->status),
            ]);
    }

    /**
     * `$before` restricts to enrollments created on or before that instant
     * — a cumulative-as-of-a-date count, used by `getStats()` to build the
     * "previous period" baseline for `active_students_delta`. `null`
     * (the default) is the plain, unfiltered current count.
     */
    private function activeStudentsCount(?int $orgId, ?CarbonImmutable $before = null): int
    {
        return User::query()
            ->role(RolesEnum::ALUNO->value)
            ->join('course_user', 'course_user.user_id', '=', 'users.id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->where('course_user.status', 'active')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->when($before !== null, fn ($query) => $query->where('course_user.created_at', '<=', $before))
            ->distinct()
            ->count('users.id');
    }

    /**
     * `$before` restricts to certificates issued on or before that instant
     * — see `activeStudentsCount()`'s docblock for why. `null` (the
     * default) is the plain, unfiltered current count.
     */
    private function certificatesIssuedCount(?int $orgId, ?CarbonImmutable $before = null): int
    {
        return Certificate::query()
            ->join('courses', 'courses.id', '=', 'certificates.course_id')
            ->whereNull('certificates.revoked_at')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->when($before !== null, fn ($query) => $query->where('certificates.issued_at', '<=', $before))
            ->count();
    }

    /**
     * Certificates issued strictly since `$since` — the "certificados
     * prontos" recency window in `attentionCounts()`. Opposite direction
     * from `certificatesIssuedCount()`'s `$before`, so it is its own
     * method rather than a shared "cutoff" parameter that would silently
     * flip meaning depending on the caller.
     */
    private function certificatesIssuedSince(?int $orgId, CarbonImmutable $since): int
    {
        return Certificate::query()
            ->join('courses', 'courses.id', '=', 'certificates.course_id')
            ->whereNull('certificates.revoked_at')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->where('certificates.issued_at', '>=', $since)
            ->count();
    }

    /**
     * Pending `QuizAttempt`s awaiting a Gestor's manual essay grade,
     * joined by hand through `quiz.lesson.module.course.org_id` — none of
     * those tables carry `OrgScope` (cascade-inherited tenancy).
     */
    private function pendingEssaysCount(?int $orgId): int
    {
        return DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->join('courses', 'courses.id', '=', 'modules.course_id')
            ->where('quiz_attempts.status', 'awaiting_manual_grading')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->count();
    }

    /**
     * Pending `ForumReport`s. `postable_type`/`postable_id` are a
     * pseudo-polymorphic pair with no DB FK (see `ForumReport::postable()`),
     * so this unions the two concrete postable tables — `forum_topics`
     * (directly `org_id`-scoped) and `forum_replies` (scoped via its
     * parent `forum_topics.org_id`) — rather than resolving each row's
     * `postable()` in PHP.
     */
    private function pendingForumReportsCount(?int $orgId): int
    {
        $topicReports = DB::table('forum_reports')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_reports.postable_id')
            ->where('forum_reports.postable_type', ForumTopic::class)
            ->where('forum_reports.status', 'pending')
            ->when($orgId !== null, fn ($query) => $query->where('forum_topics.org_id', $orgId));

        $replyReports = DB::table('forum_reports')
            ->join('forum_replies', 'forum_replies.id', '=', 'forum_reports.postable_id')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_replies.topic_id')
            ->where('forum_reports.postable_type', ForumReply::class)
            ->where('forum_reports.status', 'pending')
            ->when($orgId !== null, fn ($query) => $query->where('forum_topics.org_id', $orgId));

        return $topicReports->count() + $replyReports->count();
    }

    /**
     * The start of the "current period" for a `x-ui.chip` value ('7d',
     * '30d', or 'year'); anything else falls back to '30d'. Only the
     * length of the window matters here — `getStats()` still returns
     * all-time current values, this merely locates the previous-period
     * baseline cutoff.
     */
    private function periodStart(string $period): CarbonImmutable
    {
        return match ($period) {
            '7d' => $this->daysAgo(7),
            'year' => CarbonImmutable::now()->startOfYear(),
            default => $this->daysAgo(30),
        };
    }

    private function daysAgo(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($days);
    }

    /**
     * Percentage change of `$current` over `$previous`, formatted pt-BR
     * with an explicit sign (e.g. "+4,2%", "-3,0%") — never a bare "0%",
     * since a `null` previous baseline (no activity before the period)
     * means "no comparison is possible", not "no change".
     */
    private function percentDelta(int $current, int $previous): ?string
    {
        if ($previous === 0) {
            return null;
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);
        $sign = $percent >= 0 ? '+' : '-';

        return $sign.number_format(abs($percent), 1, ',', '.').'%';
    }

    private function completionRate(?int $orgId): int
    {
        $average = DB::table('course_user')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->avg('course_user.progress_percentage');

        return (int) round((float) ($average ?? 0));
    }

    /**
     * per-Organization
     * counts for ALL Organizations (Admin-only, non-impersonated view), in a
     * single N+1-free query via correlated subqueries. Zero-filled when an
     * Organization has no related data. Never reads `Auth::user()`/
     * `session('active_org_id')` — that branching belongs to the controller.
     *
     * - `students_count`: distinct Users with role `aluno`, `status = active`,
     *   directly owned by the Organization (`users.org_id`), not
     *   enrollment-derived (different shape than `active_students` above).
     * - `courses_count`: `courses.org_id = organizations.id`, bypassing
     *   `Course`'s `OrgScope` (raw `DB::table` query, not `Course::query()`)
     *   so an Admin sees every Organization's courses regardless of the
     *   acting user's own tenant context.
     * - `certificates_count`: certificates joined through `courses.org_id`,
     *   excluding revoked ones (mirrors `certificatesIssuedCount()`).
     *
     * @return Collection<int, object{id: int, name: string, students_count: int, courses_count: int, certificates_count: int}>
     */
    public function organizationsSummary(): Collection
    {
        $studentsCount = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', RolesEnum::ALUNO->value)
            ->where('users.status', 'active')
            ->whereColumn('users.org_id', 'organizations.id')
            ->selectRaw('count(distinct users.id)');

        $coursesCount = DB::table('courses')
            ->whereColumn('courses.org_id', 'organizations.id')
            ->whereNull('courses.deleted_at')
            ->selectRaw('count(*)');

        $certificatesCount = DB::table('certificates')
            ->join('courses', 'courses.id', '=', 'certificates.course_id')
            ->whereColumn('courses.org_id', 'organizations.id')
            ->whereNull('certificates.revoked_at')
            ->selectRaw('count(*)');

        return Organization::query()
            ->select(['organizations.id', 'organizations.name'])
            ->selectSub($studentsCount, 'students_count')
            ->selectSub($coursesCount, 'courses_count')
            ->selectSub($certificatesCount, 'certificates_count')
            ->orderBy('organizations.name')
            ->get()
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'name' => $row->name,
                'students_count' => (int) $row->students_count,
                'courses_count' => (int) $row->courses_count,
                'certificates_count' => (int) $row->certificates_count,
            ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => 'Em andamento',
        };
    }

    private function statusBadgeVariant(string $status): string
    {
        return match ($status) {
            'completed' => 'neutral',
            'cancelled' => 'accent-2',
            default => 'accent',
        };
    }
}
