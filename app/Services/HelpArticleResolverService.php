<?php

namespace App\Services;

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

    public function resolveActiveOrgId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return session('active_org_id');
        }

        return $user->org_id;
    }

    public function queryAccessibleArticles(?int $orgId = null): Builder
    {
        $activeOrgId = $orgId ?? $this->resolveActiveOrgId();

        return HelpArticle::withoutGlobalScopes()->where(function (Builder $query) use ($activeOrgId): void {
            $query->whereNull('org_id');

            if ($activeOrgId !== null) {
                $query->orWhere('org_id', $activeOrgId);
            }
        });
    }

    public function findAccessibleBySlug(string $slug, ?int $orgId = null): ?HelpArticle
    {
        return $this->queryAccessibleArticles($orgId)->where('slug', $slug)->first();
    }
}
