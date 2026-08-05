---
name: navigation-conventions
description: >
  Coding conventions for the Dynamic Navigation Menu domain (SPEC-17): how
  to add/edit a sidebar or topbar entry in `NavigationRegistry`, the
  mandatory role/route-name/active-pattern rules, how to register a new
  badge, the Blade `@foreach` contract the sidebar/topbar must keep, and
  the Dusk `@sidebar-{key}-link` selector convention. Follow before
  touching `app/Services/Navigation/*`, the layout Blade components, or
  their tests.
license: MIT
metadata:
  feature: navigation
  role: conventions
  specs:
    - spec/specs/17-dynamic-navigation-menu-and-access-control.md
---

# Navigation Conventions

## Adding / Editing a Menu Item

1. **Declare it once** in `NavigationRegistry::items()` as a new
   `NavigationItem(...)`. Do NOT add `@if/@hasanyrole` or `route(...)`
   calls in `sidebar.blade.php` / `topbar.blade.php` — the Blade only
   loops.
2. **`route` MUST be a real registered route name** from `routes/web.php`
   (RF36). Legacy names like `admin.students.index` / `admin.courses.index`
   / `student.forum.index` are explicitly forbidden — they degrade to dead
   `#` links. If unsure, run `vendor/bin/sail artisan route:list --name=`.
3. **`roles` MUST match the route's `role:` middleware** (RN40 parity).
   If the route is `role:admin|gestor`, the item's `roles` is
   `['admin','gestor']`. Admin-exclusive items use `['admin']` (e.g.
   `organizations` — RN39).
4. **`activePatterns`** — use `routeIs()` wildcards broad enough to cover
   every sub-route that should keep the parent highlighted
   (`['users.*']`, `['courses.*','modules.*','lessons.*','quizzes.*']`).
   Never narrow to a single exact route name.
5. **`section`** — one of `Administração` / `Aprendizado` (declared in
   `NavigationRegistry::sectionOrder()`). Sections with zero visible
   items for the acting user are dropped automatically — do not add
   per-section `@hasanyrole` guards.

## Adding a Badge (RF38)

1. Add the counting method to `NavigationBadges` (invokable, takes the
   acting `User`, returns `int`).
2. **Org-scope it correctly** — see `navigation-architecture`. If the
   underlying model has `OrgScope`, a `whereHas` through that relation
   suffices; if not (e.g. `ForumReport`), filter via `postable()` + the
   relevant Policy `view` gate. A bare unscoped `->count()` is a
   cross-tenant leak.
3. Wire it on the `NavigationItem` via `badgeCallback: $badges->yourCount(...)`.
   A zero count renders no badge automatically.

## Contextual Routes (RF39)

If an item's URL depends on the acting user (e.g. the forum needs a
`{course}` context), pass a `routeResolver` closure returning the URL
string or `null` (null hides the item). The `route` field is inert when a
resolver is set — leave a sensible label-only name and document it.

## Blade Contract

`sidebar.blade.php` / `topbar.blade.php` receive `$navigationSections`
(from `NavigationComposer`). Each `$item` is an array with keys:
`key`, `label`, `url`, `active` (bool), `badge` (int|string|null),
`icon` (raw SVG inner markup), `section`. Render badges only when
`$item['badge'] !== null`. Every nav `<a>` MUST carry
`dusk="sidebar-{$item['key']}-link"` (and the mobile variant
`-link-mobile`) — Dusk tests select on these.

## Service Registration

`NavigationRegistry` and `NavigationService` are singletons bound in
`AppServiceProvider::register()`; `NavigationService` gets the resolved
`Request` injected (for `routeIs()` reads). The composer is bound in
`AppServiceProvider::boot()` to both layout components — do not create a
separate `ComposerServiceProvider`.

## Forbidden Patterns

- `href="#"` for any nav item (RF36).
- `Route::has('...') ? route('...') : '#'` ternaries in Blade — the
  service hides unresolvable items instead.
- `@hasanyrole` / `@if(auth()->user()->hasRole(...))` in the sidebar/topbar
  for item visibility — use the registry's `roles` array.
- Hardcoding `$adminStudentsRoute` / `$adminCoursesRoute` style locals at
  the top of a Blade file.
