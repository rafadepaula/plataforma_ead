---
name: help-architecture
description: >
  Landing Page & Contextual Help Center domain (SPEC-11): `help_articles`
  schema (directly org-scoped, nullable `org_id` mean global),
  `HelpArticleResolverService` org-specific-then-global fallback, why
  `<x-help-button>` resolve own `org_id` outside `OrgScope` instead of
  relying on it, 100%-of-screens coverage requirement (RF12/RN05) spanning
  authenticated (`layouts.app`) and public/guest screens. Use when
  designing or reviewing feature touching `HelpArticle` data, before
  adding new `target_page_key`, or when deciding how new screen (staff,
  guest, fully public) wires in contextual help.
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

RF11 is standalone public Landing Page (`GET /`, `landing.show`). RF12 /
RN05 is Contextual Help Center: every screen in platform — staff
(`layouts.app`), guest/auth (`layouts.guest`), and fully public/standalone
documents (Landing Page, `/convite/{token}`, `/validar-certificado/{hash}`)
— must carry `<x-help-button key="...">` that open modal with article
content, or render inert if no article authored yet for that `key`. This
feature never mutate any other domain data. It only read `help_articles`
and render on top of every other feature screens.

## Schema (SPEC-00 §2.1.20)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `help_articles` | `org_id` (nullable, FK→organizations, cascadeOnDelete), `title`, `slug` (unique), `category` (nullable), `target_page_key` (nullable, indexed), `content` (longText) | **Directly org-scoped** (`OrgScope` on the model) but `org_id` is **nullable** — `null` means a *global* article, visible to every Organization; a non-null `org_id` means an org-specific override for the same `target_page_key` |

`target_page_key` have no uniqueness constraint by itself — same key can
have both global row (`org_id = null`) and one org-specific row per
Organization at same time. `slug` is only globally-unique column, used
for admin-facing article management, not for resolution.

## Resolution: `HelpArticleResolverService`

`HelpArticleResolverService::resolve(string $targetPageKey, ?int $orgId)`
is single source of truth for which `HelpArticle` given screen show:

1. If `$orgId` not `null`, look for row matching both `target_page_key`
   **and** `org_id = $orgId`. If found, return it.
2. Otherwise (or if step 1 found nothing), look for row matching
   `target_page_key` with `org_id IS NULL` (global article).
3. If neither exist, return `null`. Caller must render inert state, never
   throw, never 500.

Service query with `HelpArticle::withoutGlobalScopes()` on purpose —
resolution must compare against *caller-supplied* `$orgId` (possibly
impersonated org, or `null` for anonymous/public screen) instead of
`OrgScope` own `Auth::user()`/`session('active_org_id')` resolution.
Relying on scope here break both Admin impersonation (whose own `org_id`
is `null`) and every guest-facing page.

## `<x-help-button>` Resolves `org_id` Itself, Independent of `OrgScope`

`App\View\Components\HelpButton` mirror `OrgScope` own Admin-vs-org-user
branching (see `tenancy-conventions`) but read
`session('active_org_id')`/`Auth::user()` directly in own
`resolveOrgId()`, instead of leaning on scoped query:

- No authenticated user (`Auth::user()` is `null`, e.g. Landing Page,
  `/convite/*`, `/validar-certificado/*`, and `layouts.guest` screens):
  `org_id = null`, resolve only global article.
- `role:admin`: `session('active_org_id')` (currently-impersonated Org,
  or `null` if not impersonating any).
- `role:gestor` / `role:aluno`: `$user->org_id` directly.

This mirror, but do not reuse, `OrgScope` resolution logic — see
`tenancy-architecture` for why guest must resolve to `org_id = null`
instead of throwing `UnresolvedOrgContextException`. That exception
reserved for write paths requiring *known* tenant. Help-article
resolution is read path, must always degrade gracefully.

## 100%-of-Screens Coverage (RF12/RN05)

RN05 allow content authoring to lag behind coverage — "button itself must
always be present" even when no `HelpArticle` written yet for given
`target_page_key`. Then button render disabled/inert (see
`help-conventions` for exact Blade branch). Every screen bucket must
carry component:

| Bucket | Wiring point | Screens |
| --- | --- | --- |
| Staff (authenticated) | `components/layout/topbar.blade.php`, once, keyed by `Route::currentRouteName()` | every `layouts.app`-based Admin/Gestor/Aluno screen |
| Guest (unauthenticated, session-aware layout) | `layouts/guest.blade.php`, once, keyed by `Route::currentRouteName()` | `auth/login`, `auth/forgot-password`, `auth/reset-password` |
| Standalone public documents | inline per view, explicit `key` | `landing/show.blade.php` (`key="landing"`), `public/certificates/show.blade.php` (`key="certificates.verify"`) |

`convite/show.blade.php` (`key="invitation.show"`) is **not** in the
standalone bucket — it `@extends('layouts.guest')`, so its help button is
part of the Guest row above.

Staff and guest buckets wired **once** at layout level and key off
current route name automatically — new screen added to either layout get
help coverage free. Standalone-document bucket has no route-keyed shared
layout to hook into — since the `front_redesign` Fase 7 pass both share
`<x-layout.public>` (see `bootstrap-conventions` §2) for `<!doctype html>`
boilerplate, but that component does not itself key or place any
`<x-help-button>` — so every new fully-public screen must still remember
to add `<x-help-button key="...">` explicitly inside its own slot. No
automatic enforcement for this bucket beyond code review and feature own
test suite.

## Why `HelpArticle` Has No `role_id`/Content-Per-Role Split

Single `target_page_key` resolve to one article regardless of which role
view it — `HelpArticleResolverService` never branch on `RolesEnum`.
Per-role differentiation, if ever needed, would be new `target_page_key`
per role-specific screen (e.g. Admin `organizations.index` vs Aluno
`student.courses.index` already different keys because different routes),
not new column on `help_articles`. Do not add role-based branching inside
resolver.
