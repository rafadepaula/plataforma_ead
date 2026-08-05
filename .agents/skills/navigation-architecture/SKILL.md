---
name: navigation-architecture
description: >
  Explains the Dynamic Navigation Menu & Access-Control domain (SPEC-17):
  the `NavigationRegistry` → `NavigationService` → `NavigationComposer`
  pipeline that replaced the previously hardcoded Blade sidebar/topbar,
  the three-gate access filter (role allow-list → permission checks →
  contextual route resolver) guaranteeing menu/route-middleware parity
  (RN40), the active-pattern sub-route highlighting (RF37), the org-scoped
  pending-count badges (RF38, RN41), and the contextual Aluno forum URL
  (RF39). Use whenever adding/changing a sidebar or topbar entry, wiring a
  new role-gated screen into the menu, or auditing link/permission parity.
license: MIT
metadata:
  feature: navigation
  role: architecture
  specs:
    - spec/specs/17-dynamic-navigation-menu-and-access-control.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Navigation Architecture

## Overview

SPEC-17 retired the imperative, role-guarded Blade sidebar/topbar (which
hardcoded non-existent route names like `admin.students.index` /
`admin.courses.index` / `student.forum.index` that silently degraded to
dead `#` links) in favour of a centralized, declarative pipeline:

1. **`App\Services\Navigation\NavigationRegistry`** — the single
   read-only declaration of every menu item (`NavigationItem` value
   object: `key`, `label`, `route`, `activePatterns`, `icon`, `roles`,
   `permissions`, `section`, optional `routeResolver`/`badgeCallback`).
2. **`App\Services\Navigation\NavigationService`** — stateless filter that
   reads the registry, applies the three-gate access pipeline per item
   for the acting user, resolves URLs and badges, drops empty sections,
   and returns a list of `NavigationSection` value objects.
3. **`App\Http\View\Composers\NavigationComposer`** — bound (in
   `AppServiceProvider::boot()`) to `components.layout.sidebar` and
   `components.layout.topbar`; injects `$navigationSections` plus the
   shell-only `$brandUrl`/`$loginUrl`/`$logoutUrl`.

The Blade components now only `@foreach($navigationSections as $section)`
→ `@foreach($section->items as $item)`. **No role checks, `Route::has()`
guards, or `route()` calls live in Blade anymore** — adding those back
re-introduces the dead-link and link-leak classes of bugs this spec fixed.

## The Three-Gate Access Pipeline (RN38/RN40)

`NavigationService::resolve()` hides an item unless ALL gates pass:

1. **`roles` allow-list** — `passesRoleGate()`: non-empty array
   intersected against `$user->hasRole(...)`; empty array = any
   authenticated user. This is the menu's mirror of the route's own
   `role:admin|gestor` / `role:aluno` middleware group.
2. **`permissions`** — `passesPermissionGate()`: optional explicit
   `$user->can(...)` checks (AND-ed); empty = no extra permission gate.
3. **`routeResolver` / `Route::has()`** — `resolveUrl()`: if a resolver
   closure is set, its non-null return is the URL (null hides the item —
   used by RF39's contextual forum link). Otherwise the item's `route`
   name MUST be a registered route or the item is hidden (RF36 — never a
   dead `#` link).

Parity rule (RN40): the `roles` declared on a `NavigationItem` MUST match
the `role:` middleware on the route it points at. If a route returns 403
for a role, that role must not appear in the item's `roles` array.

## Active-Pattern Highlighting (RF37)

Each item declares `activePatterns` — `routeIs()` wildcards. The parent
"Alunos & Usuários" item uses `['users.*']` so it stays highlighted on
`users.create` / `users.edit` sub-routes. `NavigationService::isActive()`
reads the acting `Request` (injected via the service constructor) and
returns true if any pattern matches. **Do not** narrow a pattern to a
single route name or sub-pages will lose the parent highlight.

## Org-Scoped Badges (RF38/RN41)

`NavigationBadges` holds the two pending-count resolvers wired into the
registry via `badgeCallback`:

- **`pendingEssayCount()`** — `QuizAttempt` where
  `status = awaiting_manual_grading` AND `whereHas('quiz.lesson.module.course')`.
  The `whereHas` subquery engages `Course`'s `OrgScope`, so the count is
  org-scoped for a Gestor and scoped to `session('active_org_id')` for an
  Admin impersonating an Org.
- **`pendingForumReportCount()`** — `ForumReport` has **no** `OrgScope`
  (pseudo-polymorphic, no `org_id` column), so it is scoped here by
  resolving each pending report's `postable()` and keeping only those the
  acting user can `view` via `ForumTopicPolicy`/`ForumReplyPolicy`'s
  same-org gate — the exact gate `ForumModerationController::index()` uses
  on the page the badge links to. **Never** replace this with a bare
  `ForumReport::where('status','pending')->count()` — that leaks a
  cross-tenant count (the original review-found RN41 violation).

A zero count renders no badge at all (`resolveBadge()` returns null).

## Contextual Aluno Forum URL (RF39)

The forum lives under `courses/{course}/forum`, so there is no canonical
URL. `NavigationRegistry::resolveForumRoute()` resolves the most recently
updated active enrollment's course and links to its `forum.index`, or
returns `null` (hiding the item) when the Aluno has no active enrollment.
The `route` field on a resolver-driven item is inert — the resolver is
the sole source of the href.

## Relationship to Other Modules

- **`tenancy-*`** — `OrgScope` (on `Course`) is what makes the essay
  badge org-scoped; the forum badge cannot lean on it and must use the
  Policy gate instead (see above).
- **`auth-orgs-*`** — `roles` arrays here mirror the Spatie role names
  (`admin`/`gestor`/`aluno`) used by the `role:` route middleware.
- **`forum-*` / `quizzes-*`** — the moderation/essay-grading badge
  counts must stay consistent with those modules' controller scoping.
- **`dashboard-*`** — the brand link (`NavigationComposer::brandUrl()`)
  routes Admin/Gestor to `admin.dashboard`, Aluno to
  `student.courses.index`.
