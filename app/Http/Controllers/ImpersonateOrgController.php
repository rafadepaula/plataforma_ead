<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * lets an Admin set/clear `session('active_org_id')`,
 * which `OrgScope` reads to filter every org-scoped model to a single
 * Organization (see `tenancy-architecture` skill). Route access is
 * restricted to `role:admin` (see `routes/web.php`).
 *
 * `impersonate.start`/`impersonate.stop` are audited here
 * (not via `AuditableTrait`, since no model mutation happens — only the
 * session). Audit failures never break the primary flow (see
 * `audit-logs-conventions`).
 */
class ImpersonateOrgController extends Controller
{
    public function store(Organization $organization): RedirectResponse
    {
        if ($organization->status !== 'active') {
            throw ValidationException::withMessages([
                'organization' => 'Só é possível assumir o contexto de uma Organização ativa.',
            ]);
        }

        session(['active_org_id' => $organization->id]);

        $adminId = Auth::id();

        try {
            AuditService::log(
                event: 'impersonate.start',
                orgId: $organization->id,
                userId: $adminId,
                payload: [
                    'admin_id' => $adminId,
                    'target_org_id' => $organization->id,
                    'target_org_name' => $organization->name,
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', "Contexto alterado para a Organização \"{$organization->name}\".");
    }

    public function destroy(): RedirectResponse
    {
        $originalOrgId = session('active_org_id');
        $adminId = Auth::id();

        session()->forget('active_org_id');

        try {
            AuditService::log(
                event: 'impersonate.stop',
                orgId: null,
                userId: $adminId,
                payload: [
                    'admin_id' => $adminId,
                    'original_org_id' => $originalOrgId,
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        // UX-002 §4.4 — the "Sair do contexto" control now lives in the
        // topbar of every screen, so `back()` could return the Admin to a
        // screen whose content depended on the context just dropped
        // (e.g. `/courses` under impersonation). The destination is
        // therefore deterministic.
        return redirect()->route('admin.dashboard')
            ->with('success', 'Contexto de Organização encerrado.');
    }
}
