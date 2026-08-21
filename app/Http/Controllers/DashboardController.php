<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * `GET /admin/dashboard` (route name `admin.dashboard`,
 * see `dashboard-conventions` for why that exact name is load-bearing).
 * Restricted to `role:admin|gestor` (see `routes/web.php`), no dedicated
 * Policy — mirrors the `quiz-attempts.pending`/`forum-moderation.index`
 * role-middleware-only precedent.
 *
 * `DashboardMetricsService` never reads `Auth::user()`/
 * `session('active_org_id')` itself (see its own docblock) — the acting
 * org is resolved here, replicating `OrgScope`'s own "admin + no active
 * Impersonate Org session => no filter (global)" branch, since
 * `Certificate`/`course_user`/`User` don't inherit that behavior.
 */
class DashboardController extends Controller
{
    /**
     * The only values the `x-ui.chip` period filter is allowed to send —
     * anything else (missing, tampered, stale bookmark) silently falls
     * back to `DEFAULT_PERIOD` rather than erroring.
     *
     * @var list<string>
     */
    private const ALLOWED_PERIODS = ['7d', '30d', 'year'];

    private const DEFAULT_PERIOD = '30d';

    public function __construct(protected DashboardMetricsService $dashboardMetricsService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $orgId = $this->resolveViewingOrgId($request);
        $period = $this->resolvePeriod($request);

        $isGlobalAdminView = $user->hasRole(RolesEnum::ADMIN->value) && $orgId === null;

        return view('dashboard.index', [
            'user' => $user,
            'stats' => $this->dashboardMetricsService->getStats($orgId, $period),
            'recentEnrollments' => $this->dashboardMetricsService->recentEnrollments($orgId),
            'attentionCounts' => $this->dashboardMetricsService->attentionCounts($orgId),
            'mostCompletedCourses' => $this->dashboardMetricsService->mostCompletedCourses($orgId),
            'organizationsSummary' => $isGlobalAdminView
                ? $this->dashboardMetricsService->organizationsSummary()
                : null,
            'period' => $period,
            'isGlobalAdminView' => $isGlobalAdminView,
            'activeOrganizationName' => $orgId ? Organization::query()->find($orgId)?->name : null,
        ]);
    }

    /**
     * A request-supplied `period` is only ever used to pick which
     * comparison window `DashboardMetricsService::getStats()` uses for its
     * deltas — never to filter which rows belong to which Organization, so
     * unlike `resolveViewingOrgId()` it is safe to trust as-is once
     * validated against the allow-list.
     */
    private function resolvePeriod(Request $request): string
    {
        $period = $request->query('period');

        return in_array($period, self::ALLOWED_PERIODS, true) ? $period : self::DEFAULT_PERIOD;
    }

    /**
     * Mirrors `OrgScope::bootOrgScope()`'s own admin-global-vs-gestor
     * -own-org resolution (see the `tenancy-architecture` skill) — never
     * trusts a request-supplied `org_id`.
     */
    protected function resolveViewingOrgId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            $activeOrgId = session('active_org_id');

            return $activeOrgId ? (int) $activeOrgId : null;
        }

        return $user->org_id ? (int) $user->org_id : null;
    }
}
