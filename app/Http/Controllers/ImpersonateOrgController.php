<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * SPEC-04 §2 / UC18 — lets an Admin set/clear `session('active_org_id')`,
 * which `OrgScope` reads to filter every org-scoped model to a single
 * Organization (see `tenancy-architecture` skill). Route access is
 * restricted to `role:admin` (see `routes/web.php`).
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

        return back()->with('success', "Contexto alterado para a Organização \"{$organization->name}\".");
    }

    public function destroy(): RedirectResponse
    {
        session()->forget('active_org_id');

        return back()->with('success', 'Contexto de Organização encerrado.');
    }
}
