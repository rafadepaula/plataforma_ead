---
name: help-maintenance
description: >
  Debugging, testing, and edge-case guide for the Landing Page &
  Contextual Help Center feature (SPEC-11): the mandatory PHPUnit/Dusk
  test files, common `target_page_key`/fallback/`withoutEvents()` failure
  modes, the no-Alpine.js `.dialog-backdrop` Dusk-wait gotcha, and the
  100%-coverage-vs-content-authoring gap. Use when `LandingPageTest`,
  `HelpCenterTest`, or `ContextualHelpFallbackTest` is failing; a help
  button doesn't render on a new screen; the wrong article (org-specific
  vs. global) is resolved; or `HelpCenterDuskTest` flakes on opening the
  modal.
license: MIT
metadata:
  feature: help
  role: maintenance
  specs:
    - spec/specs/11-landing-page-and-contextual-help-center.md
---

# Help Center Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-11 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/LandingPageTest.php` — the public `GET /` route renders
  without a session, shows the marketing copy, and carries
  `<x-help-button key="landing">`.
- `tests/Feature/HelpCenterTest.php` — `<x-help-button>` renders a
  resolved article on an Admin, a Gestor, and an Aluno authenticated
  screen (`assertSee` on both the `dusk="help-button-{key}"` element and
  the article's title/content), plus the inert-disabled branch when no
  article exists for a `target_page_key`.
- `tests/Feature/ContextualHelpFallbackTest.php` — `HelpArticleResolverService`'s
  fallback contract in isolation: org-specific wins over global,
  global-only serves when no org-specific row exists, `null` when
  neither exists, and the anonymous (`$orgId = null`) case only ever
  resolves the global article even when another Organization has one.
- `tests/Unit/Services/HelpArticleResolverServiceTest.php`,
  `tests/Unit/Models/HelpArticleTest.php`,
  `tests/Unit/View/Components/HelpButtonTest.php` — narrower unit-level
  coverage of the same resolver, the model's `OrgScope`/nullable-`org_id`
  behavior, and the component's `resolveOrgId()` branching per role.
- `tests/Browser/HelpCenterDuskTest.php` (Dusk E2E) — an Aluno opens the
  help button on `student.courses.index` and sees the resolved article's
  title/content inside the modal.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=LandingPageTest
vendor/bin/sail artisan test --filter=HelpCenterTest
vendor/bin/sail artisan test --filter=ContextualHelpFallbackTest
vendor/bin/sail dusk --filter=HelpCenterDuskTest
```

Dusk tests use `DatabaseMigrations`, never `RefreshDatabase` (Dusk runs in
a separate HTTP process against the same DB connection) — see
`laravel-dusk`.

## Common Failure Modes

- **Help button doesn't render on a new screen.** Confirm the screen
  actually extends `layouts.app` or `layouts.guest` (both wire
  `<x-help-button>` once, at the layout level — see
  `help-architecture`'s coverage table). A bespoke standalone document
  (its own `<!DOCTYPE html>`, like `landing/show.blade.php` or
  `public/certificates/show.blade.php`) has no shared layout to inherit
  from and must add `<x-help-button key="...">` explicitly; this is the
  one bucket with no automatic enforcement, so a missing button here is a
  wiring omission, not a framework bug.
- **Wrong article resolves (org-specific instead of global, or vice
  versa).** Check `target_page_key` matches **exactly**, including full
  dotted route name, between the seeded `HelpArticle` and the `key` the
  screen actually resolves at runtime (`Route::currentRouteName()` for
  staff/guest screens, the literal string for standalone documents) — a
  typo'd key silently falls through to "no article" (inert button), not
  an error. Then check `$orgId`: an Admin with no active "Impersonate
  Org" session resolves `org_id = null`, so it will only ever see the
  global article even if an org-specific one exists for some other Org.
- **`UnresolvedOrgContextException` (or a silently wrong `org_id`) when
  seeding a `HelpArticle` meant to be global.** `HelpArticle`'s
  `creating` hook (`OrgScope`) stamps `org_id` from
  `session('active_org_id')`/the acting user regardless of what the
  factory set — wrap the creation in `HelpArticle::withoutEvents(fn () =>
  ...)` per `help-conventions`. Forgetting this is the most common cause
  of a `HelpCenterTest` Admin-screen assertion resolving the wrong
  (or no) article.
- **`HelpCenterDuskTest` intermittently clicks through to the wrong
  element / the modal never opens.** The `.dialog-backdrop` element ships
  with a static inline `display: flex` and is only hidden once
  `ModalManager.hideBackdropsOnLoad()` runs on `DOMContentLoaded` — this
  project has **no Alpine.js** (see `ModalManager.js`'s own docblock).
  `HelpCenterDuskTest` calls `->waitUntilMissing('.dialog-backdrop')`
  before clicking the trigger button specifically to wait out that
  plain-JS hide, not any Alpine hydration. If a future comment or fix
  attributes this wait to Alpine, that's a documentation regression —
  correct it back to referencing `ModalManager.hideBackdropsOnLoad()`.
- **Inert (disabled) button unexpectedly asserted as an error in a new
  test.** RN05 explicitly allows coverage to outpace content authoring —
  a disabled `<x-help-button>` with no `HelpArticle` yet is the
  **correct** state, not a bug. Only fail a test on a missing button
  entirely (the `dusk="help-button-{key}"` element itself absent from the
  response), never on the disabled attribute being present when no
  article was seeded.

## Coverage Gap Tracking

RN05's 100%-of-screens requirement is verified today only by the handful
of screens exercised in `HelpCenterTest`/`LandingPageTest` plus manual
code review of each layout/standalone-document wiring point — there is no
automated "every route has a help button" audit. When adding a new
standalone-document screen (a bucket with no shared layout), remember to
both wire `<x-help-button key="...">` in the view **and** add a
regression assertion for it; nothing else will catch the omission.
