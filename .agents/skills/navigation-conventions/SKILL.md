---
name: navigation-conventions
description: >
  Conventions for Dynamic Navigation Menu (SPEC-17): add/edit sidebar or
  topbar entry in `NavigationRegistry`, mandatory role/route-name/
  active-pattern rules, register a badge, Blade `@foreach` contract,
  Dusk `@sidebar-{key}-link` selector convention. Follow before touching
  `app/Services/Navigation/*`, layout Blade components, or their tests.
license: MIT
metadata:
  feature: navigation
  role: conventions
  specs:
    - spec/specs/17-dynamic-navigation-menu-and-access-control.md
---

# Navigation Conventions

## Add / Edit Menu Item

1. **Declare once** in `NavigationRegistry::items()` as `NavigationItem(...)`. No `@if/@hasanyrole`, no `route(...)` in `sidebar.blade.php`/`topbar.blade.php` — Blade only loops.
2. **`route` MUST be a registered route name** from `routes/web.php` (RF36). Legacy names (`admin.students.index`, `admin.courses.index`, `student.forum.index`) forbidden — they degrade to dead `#`. Check with `vendor/bin/sail artisan route:list --name=`.
3. **`roles` MUST match route's `role:` middleware** (RN40). Route `role:admin|gestor` = item `['admin','gestor']`. Admin-only items use `['admin']` (e.g. `organizations`, RN39).
4. **`activePatterns`** — `routeIs()` wildcards broad enough to cover every sub-route keeping parent highlighted (`['users.*']`, `['courses.*','modules.*','lessons.*','quizzes.*']`). Never narrow to one exact route name.
5. **`section`** — `Administração` or `Aprendizado` (declared in `NavigationRegistry::sectionOrder()`). Empty sections drop automatically — no per-section `@hasanyrole`.

## Add Badge (RF38)

1. Add counting method to `NavigationBadges` (invokable, takes acting `User`, returns `int`).
2. **Org-scope it** — see `navigation-architecture`. Model has `OrgScope`: `whereHas` through that relation is enough. No `OrgScope` (e.g. `ForumReport`): filter via `postable()` + Policy `view` gate. Bare unscoped `->count()` = cross-tenant leak.
3. Wire on item: `badgeCallback: $badges->yourCount(...)`. Zero count renders nothing.

## Contextual Routes (RF39)

URL depends on acting user (forum needs `{course}`): pass `routeResolver` closure returning URL string or `null` (null hides item). `route` field inert when resolver set — leave sensible name and document it.

## Blade Contract

`sidebar.blade.php`/`topbar.blade.php` get `$navigationSections` from `NavigationComposer`. Each `$item` = array with `key`, `label`, `url`, `active` (bool), `badge` (int|string|null), `icon` (raw SVG inner markup), `section`. Render badge only when `$item['badge'] !== null`. Every nav `<a>` MUST carry `dusk="sidebar-{$item['key']}-link"` (mobile variant `-link-mobile`) — Dusk selects on these.

## Service Registration

`NavigationRegistry` and `NavigationService` = singletons bound in `AppServiceProvider::register()`. `NavigationService` gets resolved `Request` injected (for `routeIs()`). Composer bound in `AppServiceProvider::boot()` to both layout components — no separate `ComposerServiceProvider`.

## Forbidden

- `href="#"` on any nav item (RF36).
- `Route::has('...') ? route('...') : '#'` ternary in Blade — service hides unresolvable items.
- `@hasanyrole` / `@if(auth()->user()->hasRole(...))` for item visibility — use registry `roles`.
- Locals like `$adminStudentsRoute` / `$adminCoursesRoute` at top of Blade.
