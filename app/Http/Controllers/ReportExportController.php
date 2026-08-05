<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Services\CsvStreamExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPEC-12 — `GET /admin/reports/{type}/export` (route name
 * `reports.export`, see `dashboard-conventions`). Streams a CSV via
 * `CsvStreamExportService`. The acting org is resolved the same way
 * `DashboardController` resolves it, replicating `OrgScope`'s own
 * "admin + no active Impersonate Org session => no filter (global)"
 * branch — never trusted from a request-supplied `org_id`. A Gestor
 * whose request carries an `org_id` for another Organization is
 * rejected with a 403 rather than silently scoped to their own org (see
 * `dashboard-conventions`'s exact guard).
 */
class ReportExportController extends Controller
{
    public function __construct(protected CsvStreamExportService $csvStreamExportService) {}

    public function stream(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();

        if (! $user->hasRole(RolesEnum::ADMIN->value) && $request->filled('org_id')
            && (int) $request->query('org_id') !== (int) $user->org_id) {
            abort(403);
        }

        return $this->csvStreamExportService->stream($type, $this->resolveViewingOrgId($request));
    }

    /**
     * Mirrors `DashboardController::resolveViewingOrgId()` — never
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
