<?php

namespace App\Services;

use App\Enums\Help\HelpAudienceEnum;
use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * resolves the `HelpArticle` shown by
 * `<x-help-button>` for a given screen: an org-specific article for
 * `$orgId` wins, otherwise the global (`org_id = null`) article for the
 * same `target_page_key` is served, otherwise `null` (no article authored
 * yet, the caller must render an inert/disabled state).
 *
 * `HelpArticle::withoutGlobalScopes()` is used deliberately: resolution
 * must compare against the *caller-supplied* `$orgId` (which may be an
 * impersonated org, or `null` for an anonymous/public screen) rather than
 * `OrgScope`'s own `Auth::user()`/`session('active_org_id')` resolution —
 * relying on the scope here would break both admin impersonation and
 * guest-facing pages (Landing Page, `/convite/*`, `/validar-certificado/*`).
 */
class HelpArticleResolverService
{
    public function resolve(string $targetPageKey, ?int $orgId): ?HelpArticle
    {
        $query = HelpArticle::withoutGlobalScopes()->where('target_page_key', $targetPageKey);

        if ($orgId !== null) {
            $orgSpecific = (clone $query)->where('org_id', $orgId)->first();

            if ($orgSpecific !== null) {
                return $orgSpecific;
            }
        }

        return (clone $query)->whereNull('org_id')->first();
    }

    /**
     * Mirrors `ResolvesOrgContext`'s branching without the failure throw:
     * an impersonating Admin resolves to `session('active_org_id')` (null
     * when not impersonating — the Admin is global), everyone else (and
     * anonymous visitors) to the host Organization from `OrgContext`.
     * `null` there means state zero (unbound host), which callers treat as
     * global-only visibility.
     */
    public function resolveActiveOrgId(): ?int
    {
        $user = Auth::user();

        if ($user && $user->hasRole(RolesEnum::ADMIN->value)) {
            return session('active_org_id');
        }

        return OrgContext::current()->orgId();
    }

    /**
     * The public wiki (`HelpCenterController`) is org-independent by design:
     * always reachable (any host, any org state, guest or not) and serving
     * ONLY global (`org_id = null`) articles. Organization-specific articles
     * are contextual in-app help (`<x-help-button>` / management CRUD), never
     * public wiki content.
     *
     * On top of the org filter, each article's `audience` (minimum role
     * required, see `HelpAudienceEnum`) is checked against the acting
     * user's role: guests and Alunos share the public (`aluno`) tier,
     * Professores/Gestores add their own tier, Admins see everything. This
     * keeps staff-facing articles (admin/gestor screens) out of the wiki
     * for users who cannot reach those screens.
     */
    public function queryGlobalArticles(): Builder
    {
        return HelpArticle::withoutGlobalScopes()
            ->whereNull('org_id')
            ->whereIn('audience', HelpAudienceEnum::visibleValuesForRole($this->currentUserRole()));
    }

    public function findGlobalBySlug(string $slug): ?HelpArticle
    {
        return $this->queryGlobalArticles()->where('slug', $slug)->first();
    }

    /**
     * Mirrors the role branch of `resolveActiveOrgId`, but returns the role
     * value itself; `null` for anonymous visitors (public wiki tier).
     */
    private function currentUserRole(): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        foreach (RolesEnum::cases() as $role) {
            if ($user->hasRole($role->value)) {
                return $role->value;
            }
        }

        return null;
    }
}
