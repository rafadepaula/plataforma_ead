<?php

namespace App\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-12 §4 — computes the Admin/Gestor dashboard's stat cards and
 * "Matrículas recentes" table (`spec/docs/mockups/07-dashboard-admin.md`
 * §3/§4).
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

    /**
     * @return array{active_students: int, certificates_issued: int, completion_rate: int, courses_count: int}
     */
    public function getStats(?int $orgId): array
    {
        return [
            'active_students' => $this->activeStudentsCount($orgId),
            'certificates_issued' => $this->certificatesIssuedCount($orgId),
            'completion_rate' => $this->completionRate($orgId),
            'courses_count' => Course::query()->count(),
        ];
    }

    /**
     * @return Collection<int, object{student_name: string, course_name: string, status_label: string, status_badge_variant: string}>
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
                'courses.title as course_name',
                'course_user.status as status',
            ])
            ->map(fn ($row) => (object) [
                'student_name' => $row->student_name,
                'course_name' => $row->course_name,
                'status_label' => $this->statusLabel($row->status),
                'status_badge_variant' => $this->statusBadgeVariant($row->status),
            ]);
    }

    private function activeStudentsCount(?int $orgId): int
    {
        return User::query()
            ->role(RolesEnum::ALUNO->value)
            ->join('course_user', 'course_user.user_id', '=', 'users.id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->where('course_user.status', 'active')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->distinct()
            ->count('users.id');
    }

    private function certificatesIssuedCount(?int $orgId): int
    {
        return Certificate::query()
            ->join('courses', 'courses.id', '=', 'certificates.course_id')
            ->whereNull('certificates.revoked_at')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->count();
    }

    private function completionRate(?int $orgId): int
    {
        $average = DB::table('course_user')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->avg('course_user.progress_percentage');

        return (int) round((float) ($average ?? 0));
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
