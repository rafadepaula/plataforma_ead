<?php

namespace App\Http\Controllers;

use App\Services\ProfessorDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Dashboard do Professor (`professor.dashboard`, `role:professor`).
 * Deliberadamente NÃO herda `DashboardController`/`DashboardMetricsService`:
 * aquele resolve Impersonate Org / `org_id` (semântica de Organização);
 * aqui o perímetro é a atribuição por curso (`course_professor` via
 * `$user->taughtCourses()`, resolvida em `ProfessorDashboardService`).
 */
class ProfessorDashboardController extends Controller
{
    public function __construct(protected ProfessorDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('professor.dashboard.index', [
            'stats' => $this->dashboardService->statCards($user),
            'oldestEssays' => $this->dashboardService->oldestPendingEssays($user),
            'forumActivity' => $this->dashboardService->forumActivity($user),
            'quickAccessCourses' => $this->dashboardService->quickAccessCourses($user),
        ]);
    }
}
