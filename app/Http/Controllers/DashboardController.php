<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardMetricsService $dashboardMetricsService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $orgId = $this->resolveViewingOrgId($request);

        $isGlobalAdminView = $user->hasRole(RolesEnum::ADMIN->value) && $orgId === null;

        return view('dashboard.index', [
            'user' => $user,
            'stats' => $this->dashboardMetricsService->getStats($orgId),
            'recentEnrollments' => $this->dashboardMetricsService->recentEnrollments($orgId),
            'attentionCounts' => $this->dashboardMetricsService->attentionCounts($orgId),
            'mostCompletedCourses' => $this->dashboardMetricsService->mostCompletedCourses($orgId),
            'organizationsSummary' => $isGlobalAdminView
                ? $this->dashboardMetricsService->organizationsSummary()
                : null,
            'isGlobalAdminView' => $isGlobalAdminView,
            'canCreateCourse' => $orgId !== null,
        ]);
    }

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
