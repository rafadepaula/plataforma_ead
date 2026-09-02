---
name: help-maintenance
description: >
  Debug, test, edge-case guide for Landing Page & Contextual Help Center:
  mandatory PHPUnit/Dusk test files, common
  `target_page_key`/fallback/`withoutEvents()` failure modes, no-Alpine.js
  declarative `bootstrap.Modal` Dusk gotcha, 100%-coverage-vs-content-authoring
  gap. Use when `LandingPageTest`, `HelpCenterTest`, or
  `ContextualHelpFallbackTest` fail; help button not render on new screen;
  wrong article (org-specific vs global) resolve; or `HelpCenterDuskTest`
  flake opening modal.
license: MIT
metadata:
  feature: help
  role: maintenance
---

# Help Center Maintenance

## Mandatory Test Coverage for This Module

Tests guard this module's contract. Must stay green (PHPUnit, no Pest):

- `tests/Feature/LandingPageTest.php` — public `GET /` route render
  without session, show marketing copy, carry
  `<x-help-button key="landing">`.
- `tests/Feature/HelpCenterTest.php` — `<x-help-button>` render resolved
  article on Admin, Gestor, Aluno authenticated screen (`assertSee` on
  both `dusk="help-button-{key}"` element and article title/content),
  plus inert-disabled branch when no article exist for `target_page_key`.
- `tests/Feature/ContextualHelpFallbackTest.php` — `HelpArticleResolverService`
  fallback contract in isolation: org-specific win over global,
  global-only serve when no org-specific row, `null` when neither exist,
  anonymous (`$orgId = null`) case only ever resolve global article even
  when another Organization have one.
- `tests/Unit/Services/HelpArticleResolverServiceTest.php`,
  `tests/Unit/Models/HelpArticleTest.php`,
  `tests/Unit/View/Components/HelpButtonTest.php` — narrower unit-level
  coverage of same resolver, model `OrgScope`/nullable-`org_id` behavior,
  component `resolveOrgId()` branching per role.
- `tests/Browser/HelpCenterDuskTest.php` (Dusk E2E) — Aluno open help
  button on `student.courses.index`, see resolved article title/content
  inside modal.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=LandingPageTest
vendor/bin/sail artisan test --filter=HelpCenterTest
vendor/bin/sail artisan test --filter=ContextualHelpFallbackTest
vendor/bin/sail dusk --filter=HelpCenterDuskTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk run in separate
HTTP process); `DatabaseMigrations` retired (per-method `migrate:fresh`)
— see `laravel-dusk`/`testing-conventions`.

## Common Failure Modes

- **Help button not render on new screen.** Confirm screen extend
  `layouts.app` or `layouts.guest` (both wire `<x-help-button>` once, at
  layout level — see `help-architecture` coverage table). Bespoke
  standalone document (own `<!DOCTYPE html>`, like
  `landing/show.blade.php` or `public/certificates/show.blade.php`) have
  no shared layout to inherit, must add `<x-help-button key="...">`
  explicitly. This bucket have no automatic enforcement — missing button
  here is wiring omission, not framework bug.
- **Wrong article resolve (org-specific instead of global, or reverse).**
  Check `target_page_key` match **exactly**, including full dotted route
  name, between seeded `HelpArticle` and `key` screen resolve at runtime
  (`Route::currentRouteName()` for staff/guest screens, literal string
  for standalone documents) — typo'd key fall silently to "no article"
  (inert button), not error. Then check `$orgId`: Admin with no active
  "Impersonate Org" session resolve `org_id = null`, so only ever see
  global article even if org-specific one exist for some other Org.
- **`UnresolvedOrgContextException` (or silently wrong `org_id`) when
  seeding `HelpArticle` meant global.** `HelpArticle` `creating` hook
  (`OrgScope`) stamp `org_id` from `session('active_org_id')`/acting user
  regardless of what factory set — wrap creation in
  `HelpArticle::withoutEvents(fn () => ...)` per `help-conventions`.
  Forget this = most common cause of `HelpCenterTest` Admin-screen
  assertion resolving wrong (or no) article.
- **`HelpCenterDuskTest` intermittently click through to wrong element /
  modal never open.** Since the Bootstrap 5.3 migration the modal is
  driven by `bootstrap.Modal` through `data-bs-toggle`/`data-bs-target`,
  so there is no hand-rolled backdrop to wait out — this project have
  **no Alpine.js** and no `ModalManager` any more. A modal that never
  opens is almost always the JS bundle missing (stale `public/build`,
  `vendor/bin/sail npm run build` not run) rather than a timing problem.
  Wait on the modal itself (`waitFor('@help-modal-{key}')`), never on a
  removed `.dialog-backdrop`.
- **Inert (disabled) button asserted as error in new test.** Coverage is
  explicitly allowed to outpace content authoring — disabled
  `<x-help-button>` with no `HelpArticle` yet is **correct** state, not
  bug. Fail test only on missing button entirely (`dusk="help-button-{key}"`
  element absent from response), never on disabled attribute present when
  no article seeded.

## Coverage Gap Tracking

The 100%-of-screens requirement is verified today only by handful of
screens exercised in `HelpCenterTest`/`LandingPageTest` plus manual code
review of each layout/standalone-document wiring point — no automated
"every route has help button" audit exist. When add new
standalone-document screen (bucket with no shared layout), remember to
both wire `<x-help-button key="...">` in view **and** add regression
assertion for it; nothing else catch omission.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
maintain this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
