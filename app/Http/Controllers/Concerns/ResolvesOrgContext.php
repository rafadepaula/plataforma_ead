<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\UnresolvedOrgContextException;
use Illuminate\Http\Request;

/**
 * RF04/RF05 — Shared tenant-context resolution for controllers that must
 * act on behalf of the current user's organization: a Gestor's own
 * `org_id`, or an impersonating Admin's `session('active_org_id')`. Never
 * reads `org_id` from the request itself.
 */
trait ResolvesOrgContext
{
    protected function resolveOrgId(Request $request): int
    {
        $user = $request->user();
        $orgId = $user->org_id ?? session('active_org_id');

        if (! $orgId) {
            throw new UnresolvedOrgContextException(
                "Não foi possível resolver org_id para {$this->orgContextAction()} (usuário #{$user->id} sem org_id e sem active_org_id em sessão)."
            );
        }

        return (int) $orgId;
    }

    /**
     * Short action phrase used in the resolution-failure message, e.g.
     * "gerenciar usuários" or "importar usuários".
     */
    protected function orgContextAction(): string
    {
        return 'resolver o contexto da organização';
    }
}
