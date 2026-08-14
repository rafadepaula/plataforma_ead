<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * SPEC-12 §4 — `GET /admin/dashboard` (route name `admin.dashboard`,
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
    public function __construct(protected DashboardMetricsService $dashboardMetricsService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $orgId = $this->resolveViewingOrgId($request);

        $isGlobalAdminView = $user->hasRole(RolesEnum::ADMIN->value) && $orgId === null;

        return view('dashboard.index', [
            'stats' => $this->dashboardMetricsService->getStats($orgId),
            'recentEnrollments' => $this->dashboardMetricsService->recentEnrollments($orgId),
            'organizationsSummary' => $isGlobalAdminView
                ? $this->dashboardMetricsService->organizationsSummary()
                : null,
        ]);
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
