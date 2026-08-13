<?php

namespace App\Services\Navigation;

use App\Models\Organization;
use App\Models\User;

/**
 * UX-002 — the single source of truth for "is this user currently acting
 * inside an impersonated Organization, and which one?".
 *
 * The raw signal (`session('active_org_id')`) is read in many places
 * (`OrgScope`, `ResolvesOrgContext`, `NavigationRegistry`), but the
 * *impersonation* reading of it is narrower than the *tenant-resolution*
 * reading: a Gestor bound to its own `org_id` resolves a tenant without
 * ever impersonating anything. Only a system Admin (no own `org_id`) can
 * be inside an impersonated context, which is exactly the rule UX-001
 * already encoded in `NavigationRegistry::resolveOperationalSection()`
 * and that this class now owns for both consumers.
 *
 * The resolved Organization is memoized per instance so the topbar (and
 * the sidebar composer render that precedes it) costs at most one query
 * per request; the service is bound as a singleton in
 * `AppServiceProvider`.
 */
final class ImpersonationContext
{
    private ?int $resolvedForOrgId = null;

    private ?Organization $resolved = null;

    /**
     * True only for a system Admin currently inside an "Impersonate Org"
     * context. Never true for a Gestor/Aluno, nor for a dual
     * Admin/Gestor account bound to its own `org_id`.
     */
    public function isImpersonating(?User $user): bool
    {
        if ($user === null || $user->org_id !== null || ! $user->hasRole('admin')) {
            return false;
        }

        return session('active_org_id') !== null;
    }

    /**
     * The impersonated Organization, or `null` when the user is not
     * impersonating — including the failure path where the session
     * still points at an Organization that has since been removed.
     */
    public function activeOrganization(?User $user): ?Organization
    {
        if (! $this->isImpersonating($user)) {
            return null;
        }

        $orgId = (int) session('active_org_id');

        if ($this->resolvedForOrgId !== $orgId) {
            $this->resolvedForOrgId = $orgId;
            $this->resolved = Organization::query()->find($orgId);
        }

        return $this->resolved;
    }
}
