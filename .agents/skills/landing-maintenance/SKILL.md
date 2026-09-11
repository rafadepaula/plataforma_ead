---
name: landing-maintenance
description: >
  Debug, test, edge-case guide for the per-Organization public Landing
  Page (`tenants/{landing_view}/landing.blade.php`): mandatory
  PHPUnit/Dusk test files, controller redirect-vs-200 matrix (state
  zero, missing blade, inactive org), dusk-snapshot-count failure after
  touching any `dusk=` attribute or adding a tenant blade, stale
  `public/build` making SCSS changes invisible, and the
  `harness:check-skills` 3-skill triad requirement. Use when
  `LandingPageControllerTest`, `LandingPageTest`, `Tenancy\LandingTest`,
  `Tenancy\ShellIdentityTest`, or `LandingPageDuskTest` fail; a landing
  band renders unstyled or at the wrong width; a new tenant blade or
  `dusk=` selector breaks the selector snapshot; or the skill audit
  reports an incomplete `landing` triad.
license: MIT
metadata:
  feature: landing
  role: maintenance
---

# Landing Page Maintenance

## Mandatory Test Coverage for This Module

Tests guard the per-tenant landing contract. Must stay green (PHPUnit,
no Pest):

- `tests/Feature/LandingPageControllerTest.php` — the controller matrix:
  200 for guest **and** authenticated aluno/gestor/admin;
  `assertViewIs('tenants.ligacerto.landing')` + `assertViewHas('organization')`
  + org brand in content; guest CTA/footer `href`s (`login`,
  `certificates.verify`); aluno CTA → `student.courses.index`; staff CTA →
  `admin.dashboard`; portal without usable blade → `assertRedirect(route('login'))`;
  state-zero host → login; inactive org landing stays 200.
- `tests/Feature/Tenancy/LandingTest.php` — host tenancy of `GET /`:
  host with `landing_view` renders that org's blade, two orgs render two
  different blades, `landing_view` null → login, `landing_view` naming a
  nonexistent blade → login, state zero → login, inactive org still
  renders the landing.
- `tests/Feature/Tenancy/ShellIdentityTest.php` — shell brand via
  `OrgIdentityComposer`: login page shows host org name, state-zero
  login shows `system_name`, Admin on a tenant host still sees
  `system_name` (`admin.dashboard` content), logged-in org screen shows
  the org brand (`topbar-org-name`).
- `tests/Feature/LandingPageTest.php` — tenant brand + key copy of the
  ligacerto blade (header brand, hero headline, "Como funciona", footer
  `Validar certificado`), guest CTA `href`s, and the
  `<x-help-button key="landing" />` inert (placeholder) vs resolved
  article branches. Shared territory with `help-maintenance`, which
  cites this file by name — keep the help-button cases here.
- `tests/Browser/LandingPageDuskTest.php` (Dusk E2E, ligacerto blade on
  the Dusk tenant host) — four methods:
  1. `test_landing_page_visitor_lifecycle` — hero copy, header link and
     hero CTA both navigate to `/login`, help modal opens.
  2. `test_authenticated_aluna_cta_points_at_the_course_catalog` —
     header link `href` becomes `student.courses.index`.
  3. `test_landing_page_responsive_contract_at_every_breakpoint` —
     resizes to 320/375/768/1024/1440: no horizontal scroll, "Como
     funciona" `.col-md-4` card full-width in mobile / ~1/3 above, 36px
     hero radius at desktop, `.landing-page` class intact at every
     width.
  4. `test_landing_bands_alternate_between_blue_wash_and_plain_surface`
     — computed `backgroundColor` per `.landing-band`: hero painted,
     "Como funciona" transparent on the page surface.

  Viewport is restored to `[1920, 1080]` at the end of the responsive
  method — the `Browser` instance is shared across methods and a leaked
  mobile width silently breaks the next one.
- `tests/Feature/Theme/DuskSelectorContractTest.php` — cross-cutting but
  landing-sensitive: pins `tests/fixtures/dusk-selectors-snapshot.json`
  (538 entries at time of writing — check the fixture, not this number)
  against every `dusk=` in the views. Landing surfaces contribute 9:
  `landing-org-logo` + `landing-login-link` ×2 + `landing-hero-cta` in
  **each** tenant blade (ligacerto, informatica) plus
  `organization-landing-view` on the org admin form.

Run narrowest first after touching the module:

```bash
vendor/bin/sail artisan test --filter=LandingPageControllerTest
vendor/bin/sail artisan test --filter=LandingPageTest
vendor/bin/sail artisan test --filter=LandingTest
vendor/bin/sail artisan test --filter=ShellIdentityTest
vendor/bin/sail artisan test --filter=DuskSelectorContractTest
vendor/bin/sail dusk --filter=LandingPageDuskTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk runs in a separate
HTTP process). See `laravel-dusk`/`testing-conventions`.

## Common Failure Modes

- **`LandingPageControllerTest` fails on a redirect (or on a 200).**
  The controller's contract is a three-rung ladder, not "always 200" and
  not "always render": state zero → `assertRedirect(route('login'))`;
  `landing_view` null **or** naming a nonexistent blade → login;
  anything with a usable blade → `assertOk()`, including an **inactive**
  org (`test_an_inactive_orgs_landing_stays_reachable`). If a rung
  moved, check `LandingPageController::show()` first, then
  `Tenancy\LandingTest` for the same ladder from the host side.
- **`DuskSelectorContractTest` fails with a count mismatch after any
  `dusk=` edit — or after adding a tenant blade.** The snapshot is a
  frozen count *and* set. A new selector on an existing blade fails it;
  so does a **new tenant blade**, even one reusing only the four
  sanctioned selectors (its 4 entries are not in the snapshot yet).
  Adding a tenant is the one legitimate case for regenerating the
  snapshot — do it deliberately in the same task as the new blade.
  `<x-ui.button>` forwards `dusk` via `$attributes->merge()`, so a
  selector added as component prop reaches the DOM even though it is not
  on a literal HTML tag.
- **SCSS change looks done but Dusk computed-style assertions fail.**
  `_public-pages.scss` is compiled by Vite; nothing in the browser
  changes until `vendor/bin/sail npm run build` runs. A stale
  `public/build` manifest also produces the opposite symptom — a band
  rendering unstyled or the hero losing its 36px radius — which gets
  misread as a SCSS bug. Rebuild first, then debug. If a
  `ViteException: Unable to locate file in Vite manifest` appears, same
  fix.
- **Landing copy edit breaks two test files at once.** Feature copy is
  pinned verbatim by `LandingPageTest` (and the Dusk visitor lifecycle
  asserts the hero headline). A copy rewrite must land the blade edit
  and the test edits in the same task.
- **Brand assertion fails on an Admin surface.** `OrgIdentityComposer`
  gives non-admins the host org's name/logo and Admins `system_name` on
  every host — an assertion expecting the org brand on an Admin-authed
  tenant page (or `system_name` for an org user) is testing the wrong
  branch. See `ShellIdentityTest` for the four canonical cases.
- **`harness:check-skills` reports an incomplete `landing` triad.**
  `scripts/check-skills.php` auto-discovers any `*-architecture` /
  `*-conventions` / `*-maintenance` directory and then demands all three
  for every discovered module prefix. All three ship together, and any
  change to this screen's contract updates them in the same task
  (auto-update protocol).
- **Editing `_public-pages.scss` breaks the public certificate screen.**
  The file is shared with `public/certificates/show.blade.php` and the
  lookup form (`.ds-band-blue`, `.ds-hero-card`, `.max-w-reading`,
  `.icon-circle-*`). A "landing-only" tweak there moves the other public
  pages too — run `--filter=PublicVerification` alongside the landing
  filter.

## Skills Triad (This Module's Own Harness Requirement)

`.agents/skills/` must always hold the complete triple for this feature:

```bash
vendor/bin/sail artisan harness:check-skills
vendor/bin/sail artisan test --compact --filter=HarnessVerificationTest
```

Auto-update protocol: any code or schema change that impacts this module
re-writes the affected skills before the task finishes. Adding a tenant
blade, changing the fallback ladder, changing the `dusk=` contract or the
brand composer each belong in a specific skill — `landing-architecture`
(controller ladder, blades, brand, tokens), `landing-conventions`
(selectors, copy, footer link), this one (tests and failure modes).
