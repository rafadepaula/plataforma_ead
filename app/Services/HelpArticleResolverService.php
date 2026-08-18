<?php

namespace App\Services;

use App\Models\HelpArticle;

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
}
