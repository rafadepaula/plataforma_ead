<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UnresolvedOrgContextException;
use App\Services\OrgContext;
use Illuminate\Http\Request;

/**
 * Shared tenant-context resolution for controllers that must act on behalf
 * of the current Organization: the request host's Organization for
 * Gestor/Professor/Aluno, or an impersonating Admin's
 * `session('active_org_id')` (an Admin with no impersonation has no org
 * context — even when browsing an Organization's host). Never reads org
 * from request input.
 */
trait ResolvesOrgContext
{
    protected function resolveOrgId(Request $request): int
    {
        $user = $request->user();
        $orgId = $user->hasRole(RolesEnum::ADMIN->value)
            ? session('active_org_id')
            : OrgContext::current()->orgId();

        if (! $orgId) {
            throw new UnresolvedOrgContextException(
                "Não foi possível resolver org_id para {$this->orgContextAction()} (usuário #{$user->id} sem organização resolvida por host ou impersonação)."
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
