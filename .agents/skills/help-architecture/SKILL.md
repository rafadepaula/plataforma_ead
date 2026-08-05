---
name: help-architecture
description: >
  Explains the Landing Page & Contextual Help Center domain (SPEC-11): the
  `help_articles` schema (directly org-scoped but nullable `org_id` means
  global), the `HelpArticleResolverService` org-specific-then-global
  fallback resolution, why `<x-help-button>` resolves its own `org_id`
  outside of `OrgScope` instead of relying on it, and the 100%-of-screens
  coverage requirement (RF12/RN05) spanning both authenticated
  (`layouts.app`) and public/guest screens. Use whenever designing or
  reviewing a feature that touches `HelpArticle` data, before adding a new
  `target_page_key`, or when deciding how a new screen (staff, guest, or
  fully public) should wire in contextual help.
license: MIT
metadata:
  feature: help
  role: architecture
  specs:
    - spec/specs/11-landing-page-and-contextual-help-center.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Help Center Architecture

## Overview

RF11 is a standalone public Landing Page (`GET /`, `landing.show`). RF12 /
RN05 is the Contextual Help Center: every screen in the platform — staff
(`layouts.app`), guest/auth (`layouts.guest`), and fully public/standalone
documents (Landing Page, `/convite/{token}`, `/validar-certificado/{hash}`)
— must carry a `<x-help-button key="...">` that opens a modal with an
article's content, or renders inert if no article has been authored yet
for that `key`. This feature never mutates any other domain's data; it
only reads `help_articles` and renders on top of every other feature's
screens.

## Schema (SPEC-00 §2.1.20)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `help_articles` | `org_id` (nullable, FK→organizations, cascadeOnDelete), `title`, `slug` (unique), `category` (nullable), `target_page_key` (nullable, indexed), `content` (longText) | **Directly org-scoped** (`OrgScope` on the model) but `org_id` is **nullable** — `null` means a *global* article, visible to every Organization; a non-null `org_id` means an org-specific override for the same `target_page_key` |

`target_page_key` has no uniqueness constraint by itself — the same key
can have both a global row (`org_id = null`) and one org-specific row per
Organization simultaneously; `slug` is the only globally-unique column,
used for admin-facing article management, not for resolution.

## Resolution: `HelpArticleResolverService`

`HelpArticleResolverService::resolve(string $targetPageKey, ?int $orgId)`
is the single source of truth for which `HelpArticle` a given screen
shows:

1. If `$orgId` is not `null`, look for a row matching both
   `target_page_key` **and** `org_id = $orgId`. If found, return it.
2. Otherwise (or if step 1 found nothing), look for a row matching
   `target_page_key` with `org_id IS NULL` (the global article).
3. If neither exists, return `null` — the caller must render an inert
   state, never throw and never 500.

The service deliberately queries with `HelpArticle::withoutGlobalScopes()`
— resolution must compare against the *caller-supplied* `$orgId`
(possibly an impersonated org, or `null` for an anonymous/public screen)
rather than `OrgScope`'s own `Auth::user()`/`session('active_org_id')`
resolution. Relying on the scope here would break both Admin
impersonation (whose own `org_id` is `null`) and every guest-facing page.

## `<x-help-button>` Resolves `org_id` Itself, Independently of `OrgScope`

`App\View\Components\HelpButton` mirrors `OrgScope`'s own
Admin-vs-org-user branching (see `tenancy-conventions`) but reads
`session('active_org_id')`/`Auth::user()` directly in its own
`resolveOrgId()`, rather than leaning on a scoped query:

- No authenticated user (`Auth::user()` is `null`, e.g. Landing Page,
  `/convite/*`, `/validar-certificado/*`, and the `layouts.guest` screens)
  → `org_id = null`, resolves only the global article.
- `role:admin` → `session('active_org_id')` (the currently-impersonated
  Org, or `null` if not impersonating any).
- `role:gestor` / `role:aluno` → `$user->org_id` directly.

This mirrors, but does not reuse, `OrgScope`'s resolution logic — see
`tenancy-architecture` for why a guest must resolve to `org_id = null`
rather than throwing `UnresolvedOrgContextException` (that exception is
reserved for write paths that require a *known* tenant; help-article
resolution is a read path that must always degrade gracefully instead).

## 100%-of-Screens Coverage (RF12/RN05)

RN05 explicitly allows content authoring to lag behind coverage — "the
button itself must always be present" even when no `HelpArticle` has been
written for a given `target_page_key` yet, in which case the button
renders disabled/inert (see `help-conventions` for the exact Blade
branch). Every screen bucket must carry the component:

| Bucket | Wiring point | Screens |
| --- | --- | --- |
| Staff (authenticated) | `components/layout/topbar.blade.php`, once, keyed by `Route::currentRouteName()` | every `layouts.app`-based Admin/Gestor/Aluno screen |
| Guest (unauthenticated, session-aware layout) | `layouts/guest.blade.php`, once, keyed by `Route::currentRouteName()` | `auth/login`, `auth/forgot-password`, `auth/reset-password` |
| Standalone public documents | inline per view, explicit `key` | `landing/show.blade.php` (`key="landing"`), `convite/show.blade.php` (`key="invitation.show"`), `public/certificates/show.blade.php` (`key="certificates.verify"`) |

The staff and guest buckets are wired **once** at the layout level and
key off the current route name automatically — a new screen added to
either layout gets help coverage for free. The standalone-document bucket
has no shared layout to hook into (each is a bespoke `<!DOCTYPE html>`
document, see `certificates-architecture`'s equivalent note), so every
new fully-public screen must remember to add `<x-help-button key="...">`
explicitly; there is no automatic enforcement for this bucket beyond
code review and the feature's own test suite.

## Why `HelpArticle` Has No `role_id`/Content-Per-Role Split

A single `target_page_key` resolves to one article regardless of which
role is viewing it — `HelpArticleResolverService` never branches on
`RolesEnum`. Per-role differentiation, if ever needed, would be a new
`target_page_key` per role-specific screen (e.g. Admin's
`organizations.index` vs. Aluno's `student.courses.index` already are
different keys because they're different routes), not a new column on
`help_articles`. Do not add role-based branching inside the resolver.
