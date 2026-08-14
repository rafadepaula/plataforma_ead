---
name: navigation-maintenance
description: >
  Maintenance for Dynamic Navigation Menu (SPEC-17): debug missing/leaked
  link, wrong active highlight, stale badge count. Test files to extend
  when adding item. Keep `NavigationRegistry` route names in sync after
  rename. Common regressions. Use when fixing navigation bug or after
  renaming/adding a route the menu points at.
license: MIT
metadata:
  feature: navigation
  role: maintenance
  specs:
    - spec/specs/17-dynamic-navigation-menu-and-access-control.md
---

# Navigation Maintenance

## Symptom -> Cause -> Fix

### Link missing for user who should see it
- **Cause:** one of three gates (`roles`, `permissions`, `routeResolver`/`Route::has()`) hides it.
- **Diagnose:** in `tinker`, `app(NavigationService::class)->build($user)`, inspect returned sections/items.
- **Fix:** item `roles` must match route `role:` middleware (RN40); user must hold any `permissions` set; `route` name must be registered (`vendor/bin/sail artisan route:list --name=<name>`).

### Restricted link leaks to wrong role (RN38/RN40)
- **Cause:** `roles` includes role the route middleware denies, or resolver returned URL for user who should not get it.
- **Fix:** narrow `roles` in `NavigationRegistry`. Extend `tests/Feature/RoleMenuVisibilityTest.php` asserting URL absent from rendered HTML for that role.

### Wrong item highlighted (RF37)
- **Cause:** `activePatterns` too broad (matches siblings) or too narrow (misses current sub-route).
- **Fix:** adjust wildcards. Verify with `tests/Unit/NavigationServiceTest::test_active_flag_*` (real `Illuminate\Routing\Route` bound to request).

### Badge count wrong / leaks across orgs (RN41)
- **Cause:** resolver not org-scoped. `QuizAttempt` scopes via `whereHas('quiz.lesson.module.course')`. `ForumReport` has NO `OrgScope` — MUST filter through `postable()` + `ForumTopicPolicy`/`ForumReplyPolicy` `view` gate (see `navigation-architecture`).
- **Fix:** mirror scoping of linked page (`EssayGradingController::pending()` / `ForumModerationController::index()`). Add two-org regression like `test_pending_forum_report_badge_never_leaks_another_orgs_reports`.

### Dead `#` link back (RF36)
- **Cause:** route renamed, registry still points at old name. Or someone added `Route::has(...) ? route(...) : '#'` in Blade.
- **Fix:** update registry `route`. Re-run `tests/Feature/RoleMenuVisibilityTest::test_admin_menu_renders_all_admin_links_and_no_dead_hash` — asserts no `sidebar-item" href="#"` in served HTML.

## After Renaming a Route

1. `grep -rn "<old-name>" app/Services/Navigation/ routes/web.php` — update registry entries and `activePatterns`.
2. Run `tests/Unit/NavigationServiceTest` (route resolution) and `tests/Feature/RoleMenuVisibilityTest` (HTML parity).
3. Update `tests/Browser/NavigationMenuDuskTest` selector if item `key` changed.

## Test Files (extend when adding item)

| File | Layer | Asserts |
| --- | --- | --- |
| `tests/Unit/NavigationServiceTest.php` | Service | Per-role visibility matrix, route resolution, badge counts (incl. cross-tenant), active flag. |
| `tests/Feature/NavigationComposerTest.php` | Composer | Injects `$navigationSections` / `$brandUrl` / login-logout URLs into layout views. |
| `tests/Feature/RoleMenuVisibilityTest.php` | HTML | No restricted URL leaks per role; no dead `#`. |
| `tests/Browser/NavigationMenuDuskTest.php` | E2E | DOM presence/absence of `@sidebar-{key}-link` per role + active class on sub-route. |

## Regressions to Watch

- Imperative `@hasanyrole`/`route()` back in Blade components — bypasses service access control.
- New badge resolver with unscoped `->count()` on model lacking `OrgScope` — cross-tenant leak.
- Composer not registered for a new layout component in `AppServiceProvider::boot()`.
- Narrowed `activePatterns` — parent loses highlight on sub-route.

---

## E2E Coverage Lives in Lifecycle Chains

`tests/Browser/` groups by **user journey (lifecycle chain)** — one method drives create, edit, state change, delete, consequence. Not by module, spec, or use case.

- **Find coverage**: chain methods may sit in a file named after another module when the journey crosses boundaries. Search `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name. Missing per-module file is **not** a gap.
- **Add coverage**: extend the journey's chain with a numbered step carrying UI **and** DB assertion. New method only for independent negatives (403, cross-tenant, other actor). New file only for genuinely new journey.
- **Debug**: stack trace points at a step — match line to its `// N.` comment. Late failure usually means earlier step did not persist.
- **Database**: no DB trait in `tests/Browser/*`; `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` = suite-wide slowdown. Files, cache, session not reset between methods.

Full rule: `testing-conventions`. Chain debug: `testing-maintenance`.
