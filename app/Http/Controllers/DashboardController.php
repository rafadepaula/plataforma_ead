<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardMetricsService $dashboardMetricsService) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $orgId = $this->resolveViewingOrgId($request);
        $period = (string) $request->query('period', '30d');

        if (! in_array($period, ['7d', '30d', 'year'], true)) {
            $period = '30d';
        }

        $stats = $this->dashboardMetricsService->getStats($orgId, $period);
        $recentEnrollments = $this->dashboardMetricsService->recentEnrollments($orgId);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'stats' => $stats,
                'recentEnrollments' => $recentEnrollments,
                'period' => $period,
            ]);
        }

        $isGlobalAdminView = $user->hasRole(RolesEnum::ADMIN->value) && $orgId === null;

        return view('dashboard.index', [
            'user' => $user,
            'period' => $period,
            'stats' => $stats,
            'recentEnrollments' => $recentEnrollments,
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
