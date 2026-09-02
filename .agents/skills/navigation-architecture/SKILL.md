---
name: navigation-architecture
description: >
  Dynamic Navigation Menu & Access-Control domain:
  `NavigationRegistry` -> `NavigationService` -> `NavigationComposer`
  pipeline that killed hardcoded Blade sidebar/topbar. Three-gate access
  filter (roles, permissions, route resolver) = menu/route-middleware
  parity. Active-pattern sub-route highlight. Org-scoped
  pending badges. Contextual Aluno forum URL. Use when
  adding/changing sidebar or topbar entry, wiring role-gated screen into
  menu, auditing link/permission parity.
license: MIT
metadata:
  feature: navigation
  role: architecture
---

# Navigation Architecture

## Overview

Dynamic navigation replaced the imperative role-guarded Blade sidebar/topbar. Old code hardcoded route names that did not exist (`admin.students.index`, `admin.courses.index`, `student.forum.index`) and degraded to dead `#` links. New pipeline is declarative and centralized:

1. **`App\Services\Navigation\NavigationRegistry`** — single read-only declaration of every menu item. `NavigationItem` value object: `key`, `label`, `route`, `activePatterns`, `icon`, `roles`, `permissions`, `section`, optional `routeResolver`/`badgeCallback`.
2. **`App\Services\Navigation\NavigationService`** — stateless filter. Reads registry, runs three-gate pipeline per item for acting user, resolves URLs and badges, drops empty sections, returns `NavigationSection` list.
3. **`App\Http\View\Composers\NavigationComposer`** — bound in `AppServiceProvider::boot()` to `components.layout.sidebar` and `components.layout.topbar`. Injects `$navigationSections` plus shell-only `$brandUrl`/`$loginUrl`/`$logoutUrl`.

Blade only does `@foreach($navigationSections as $section)` then `@foreach($section->items as $item)`. **No role check, no `Route::has()` guard, no `route()` call in Blade.** Adding them back brings dead links and link leaks straight back.

## Three-Gate Access Pipeline

`NavigationService::resolve()` hides item unless ALL gates pass:

1. **`roles` allow-list** — `passesRoleGate()`: non-empty array intersected with `$user->hasRole(...)`. Empty array = any authenticated user. Mirrors route's own `role:admin|gestor` / `role:aluno` middleware.
2. **`permissions`** — `passesPermissionGate()`: optional `$user->can(...)` checks, AND-ed. Empty = no extra gate.
3. **`routeResolver` / `Route::has()`** — `resolveUrl()`: resolver closure's non-null return = URL; null hides item (forum link). No resolver: `route` name MUST be registered or item hides — never dead `#`.

Parity rule: `roles` on a `NavigationItem` MUST match `role:` middleware of its route. Route 403s for a role, that role stays out of `roles`.

## Active-Pattern Highlight

Item declares `activePatterns` — `routeIs()` wildcards. Parent "Alunos & Usuários" uses `['users.*']`, stays highlighted on `users.create`/`users.edit`. `NavigationService::isActive()` reads acting `Request` (constructor-injected), true if any pattern matches. **Do not** narrow pattern to single route name — sub-pages lose parent highlight.

## Org-Scoped Badges

`NavigationBadges` holds two pending-count resolvers, wired via `badgeCallback`:

- **`pendingEssayCount()`** — `QuizAttempt` where `status = awaiting_manual_grading` AND `whereHas('quiz.lesson.module.course')`. `whereHas` triggers `Course`'s `OrgScope`, so count is org-scoped for Gestor and follows `session('active_org_id')` for Admin impersonating Org.
- **`pendingForumReportCount()`** — `ForumReport` has **no** `OrgScope` (pseudo-polymorphic, no `org_id` column). Scope by resolving each pending report's `postable()`, keep only those the user can `view` via `ForumTopicPolicy`/`ForumReplyPolicy` same-org gate — same gate `ForumModerationController::index()` uses on the linked page. **Never** use bare `ForumReport::where('status','pending')->count()` — cross-tenant leak.

Zero count renders no badge (`resolveBadge()` returns null).

## Contextual Aluno Forum URL

Forum lives at `courses/{course}/forum` — no canonical URL. `NavigationRegistry::resolveForumRoute()` picks most recently updated active enrollment's course, links to its `forum.index`. No active enrollment = `null`, item hidden. `route` field on resolver-driven item is inert; resolver is sole href source.

## Related Modules

- **`tenancy-*`** — `OrgScope` on `Course` makes essay badge org-scoped. Forum badge cannot lean on it, uses Policy gate.
- **`auth-orgs-*`** — `roles` arrays mirror Spatie role names (`admin`/`gestor`/`aluno`) from `role:` middleware.
- **`forum-*` / `quizzes-*`** — badge counts must match those modules' controller scoping.
- **`dashboard-*`** — brand link (`NavigationComposer::brandUrl()`): Admin/Gestor to `admin.dashboard`, Aluno to `student.courses.index`.
