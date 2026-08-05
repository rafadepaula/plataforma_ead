---
name: navigation-maintenance
description: >
  Maintenance guide for the Dynamic Navigation Menu domain (SPEC-17): how
  to debug a missing/leaked link, a wrong active highlight, or a stale
  badge count; the test files to extend when adding an item; how to keep
  `NavigationRegistry` route names in sync after a route rename; and the
  common regressions to watch for. Use when fixing a navigation bug or
  after renaming/adding a route that the menu points at.
license: MIT
metadata:
  feature: navigation
  role: maintenance
  specs:
    - spec/specs/17-dynamic-navigation-menu-and-access-control.md
---

# Navigation Maintenance

## Symptom → Cause → Fix

### A link the user should see is missing
- **Cause:** one of the three gates (`roles`, `permissions`,
  `routeResolver`/`Route::has()`) is hiding it.
- **Diagnose:** in `tinker`, `app(NavigationService::class)->build($user)`
  and check the returned sections/items — the item is absent if any gate
  failed.
- **Fix:** confirm the item's `roles` matches the route's `role:`
  middleware (RN40), that `permissions` (if set) the user actually holds,
  and that the `route` name is registered
  (`vendor/bin/sail artisan route:list --name=<name>`).

### A restricted link leaks to the wrong role (RN38/RN40)
- **Cause:** the item's `roles` array includes a role the route's
  middleware does not grant, OR a resolver returned a URL for a user who
  should not have it.
- **Fix:** narrow `roles` in `NavigationRegistry`. Add/extend the
  regression in `tests/Feature/RoleMenuVisibilityTest.php` asserting the
  URL is absent from the rendered HTML for that role.

### The wrong item is highlighted (RF37)
- **Cause:** `activePatterns` too broad (matching sibling routes) or too
  narrow (missing the current sub-route).
- **Fix:** adjust the wildcards; verify with
  `tests/Unit/NavigationServiceTest::test_active_flag_*` (real
  `Illuminate\Routing\Route` bound to the request).

### A badge count is wrong / leaks across orgs (RN41)
- **Cause:** the badge resolver is not org-scoped. `QuizAttempt` is scoped
  via the `whereHas('quiz.lesson.module.course')` subquery; `ForumReport`
  has NO `OrgScope` and MUST be filtered through `postable()` + the
  `ForumTopicPolicy`/`ForumReplyPolicy` `view` gate (see
  `navigation-architecture`).
- **Fix:** mirror the scoping of the page the badge links to
  (`EssayGradingController::pending()` / `ForumModerationController::index()`).
  Add a two-org regression test like
  `test_pending_forum_report_badge_never_leaks_another_orgs_reports`.

### Dead `#` link reappears (RF36)
- **Cause:** a route was renamed but `NavigationRegistry` still references
  the old name, OR someone added a `Route::has(...) ? route(...) : '#'`
  fallback in Blade.
- **Fix:** update the registry's `route` to the new name and re-run
  `tests/Feature/RoleMenuVisibilityTest::test_admin_menu_renders_all_admin_links_and_no_dead_hash`,
  which asserts no `sidebar-item" href="#"` exists in the served HTML.

## After Renaming a Route

1. `grep -rn "<old-name>" app/Services/Navigation/ routes/web.php` —
   update `NavigationRegistry` entries and `activePatterns`.
2. Run `tests/Unit/NavigationServiceTest` (service-level route resolution)
   and `tests/Feature/RoleMenuVisibilityTest` (rendered HTML parity).
3. Update the relevant `tests/Browser/NavigationMenuDuskTest` selector if
   the item's `key` changed.

## Test Files (extend these when adding an item)

| File | Layer | What it asserts |
| --- | --- | --- |
| `tests/Unit/NavigationServiceTest.php` | Service | Per-role visibility matrix, route resolution, badge counts (incl. cross-tenant), active-flag matching. |
| `tests/Feature/NavigationComposerTest.php` | Composer | `NavigationComposer` injects `$navigationSections` / `$brandUrl` / login-logout URLs into the layout views. |
| `tests/Feature/RoleMenuVisibilityTest.php` | Rendered HTML | No restricted URL leaks into the HTML for each role; no dead `#` link. |
| `tests/Browser/NavigationMenuDuskTest.php` | E2E (Dusk) | Live DOM presence/absence of `@sidebar-{key}-link` per role + active class on a sub-route. |

## Common Regressions to Watch

- Reintroducing imperative `@hasanyrole`/`route()` calls in the Blade
  components (bypasses the service's access control).
- A new badge resolver using an unscoped `->count()` on a model without
  `OrgScope` (cross-tenant leak).
- Forgetting to register the composer for a new layout component in
  `AppServiceProvider::boot()`.
- Narrowing `activePatterns` so a parent item loses its highlight on a
  sub-route.
