---
name: landing-maintenance
description: >
  Debug, test, edge-case guide for the public Landing Page & Component
  Showcase: mandatory PHPUnit/Dusk test files, dusk-snapshot-count failure
  after touching any `dusk=` attribute, stale `public/build` making SCSS
  changes invisible to browser assertions, and the `harness:check-skills`
  3-skill triad requirement this module itself is subject to. Use when
  `LandingPageControllerTest`, `LandingPageTest`, or
  `LandingPageDuskTest` fail; a landing band renders unstyled or at the
  wrong width; a `_public-pages.scss` media query looks dead because a
  Bootstrap `!important` utility outranks it; a new `dusk=` selector breaks the selector snapshot; or
  the skill audit reports an incomplete `landing` triad.
license: MIT
metadata:
  feature: landing
  role: maintenance
  specs:
    - spec/specs/31-public-landing-page-and-showcase.md
    - spec/specs/11-landing-page-and-contextual-help-center.md
---

# Landing Page Maintenance

## Mandatory Test Coverage for This Module

Tests guard the landing/showcase contract. Must stay green (PHPUnit, no
Pest):

- `tests/Feature/LandingPageControllerTest.php` — controller-level render
  contract: 200 for guest **and** for authenticated aluno/gestor/admin
  (the route never redirects anyone), the 7 band headings, verbatim
  hero badge/headline/lead/CTA copy, showcase card copy, `id="contato"`,
  the tonal class on the contact button, the download SVG path, footer
  copyright year and validation href.
- `tests/Feature/LandingPageTest.php` — guest + authenticated
  reachability, all 7 bands and key copy, CTA `href` routing
  (guest → `login`), and `<x-help-button key="landing">` inert vs
  populated branches. Shared territory with `help-maintenance`, which
  cites this file by name — keep the help-button cases here.
- `tests/Browser/LandingPageDuskTest.php` (Dusk E2E) — four methods, all
  driving the page through `tests/Browser/Pages/HomePage.php`
  (`->on(new HomePage)->waitFor('@headline')`, see `landing-conventions`):
  1. `test_landing_page_visitor_and_showcase_lifecycle` — headline text,
     CTA/login links navigating to `login`, showcase copy, contact anchor.
  2. `test_authenticated_user_can_visit_landing_page`.
  3. `test_landing_page_responsive_contract_at_every_breakpoint` — resizes
     to 320/375/768/1024/1440 and reads one `script()` payload per width:
     no horizontal scroll (`document.body.scrollWidth <=
     window.innerWidth`), `grid-3` track count 1 below 905px / 3 above,
     `grid-4` 1 / 2 (905-1239px) / 4 (>=1240px), header 64px vs 76px,
     `.landing-brand-name` `display:none` below 905px, footer
     `justify-content` `center` vs `space-between`, and the 36px hero
     radius at desktop.
  4. `test_landing_bands_alternate_between_blue_wash_and_plain_surface` —
     compares computed band backgrounds against probe elements so the
     showcase band stays `--surface` (not `--surface-alt`) and the mint
     step-4 circle stays distinct from steps 1-3.

  Two mechanics to preserve when editing this file: header height is read
  with `getBoundingClientRect()`, not computed `height` (`box-sizing:
  border-box` reports 75px at desktop because of the 1px
  `border-bottom`); and the responsive method restores the viewport to
  `[1920, 1080]` at the end, because the `Browser` instance is shared
  across methods and a leaked mobile width silently breaks the next
  method.
- `tests/Feature/Theme/DuskSelectorContractTest.php` — cross-cutting but
  landing-sensitive: pins
  `tests/fixtures/dusk-selectors-snapshot.json` (430 entries) against
  every `dusk=` in the views. `landing/show.blade.php` contributes 6
  entries: `contact-button`, `landing-cta-login` ×2, `landing-headline`,
  `landing-login-link` ×2. The footer's public-validation destination,
  `public/certificates/lookup.blade.php`, contributes 3 more —
  `certificate-lookup-form`, `certificate-lookup-hash`,
  `certificate-lookup-submit` — exercised by
  `tests/Browser/CertificateVerificationTest.php`
  (`test_landing_footer_leads_to_the_hash_lookup_form_which_verifies_a_typed_hash`)
  and `tests/Feature/PublicVerificationTest.php`.

Run narrowest first after touching the module:

```bash
vendor/bin/sail artisan test --filter=LandingPageControllerTest
vendor/bin/sail artisan test --filter=LandingPageTest
vendor/bin/sail artisan test --filter=DuskSelectorContractTest
vendor/bin/sail dusk --filter=LandingPageDuskTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk runs in a separate
HTTP process). See `laravel-dusk`/`testing-conventions`.

## Common Failure Modes

- **`DuskSelectorContractTest` fails with a count mismatch after any
  `dusk=` edit.** The snapshot is a frozen count *and* set. Adding a
  selector (most commonly a per-showcase-card `dusk="landing-showcase-*"`
  or a selector placed on a `<x-ui.card>`/`<x-ui.badge>` wrapper) fails
  the suite until the snapshot is deliberately regenerated; so does
  removing `contact-button`, which the spec's selector list does not
  mention but the snapshot and the Dusk test still pin. Fix is almost
  never "regenerate the snapshot" — it is "delete the new selector" (see
  `landing-conventions`). The exception is a selector that a browser test
  genuinely drives: the three `certificate-lookup-*` entries are
  legitimate and already in the 430-entry baseline — never delete those
  to make a count mismatch go away; check the count in the fixture
  itself, not against a number quoted in a skill, before concluding a
  selector is stray. Remember `<x-ui.button>` forwards `dusk` to the
  rendered element via `$attributes->merge()`, so a selector added as a
  component prop does reach the DOM even though it is not on a literal
  HTML tag.
- **SCSS change looks done but Dusk computed-style assertions fail.**
  `_public-pages.scss` is compiled by Vite; nothing in the browser
  changes until `vendor/bin/sail npm run build` runs. A stale
  `public/build` manifest also produces the opposite symptom — a band
  rendering unstyled, at 100% width, or with the old 0-radius hero —
  which gets misread as a SCSS bug. Rebuild first, then debug. If a
  `ViteException: Unable to locate file in Vite manifest` appears, same
  fix.
- **`LandingPageControllerTest` asserts a redirect and fails.** There is
  none: `LandingPageController::show()` returns `view('landing.show')`
  unconditionally for every role. The "routing" behaviour to cover is the
  CTA `href` branching in the view (guest → `route('login')`, aluno →
  `student.courses.index`, staff → `admin.dashboard`), not
  `assertRedirect()`.
- **Landing copy edit breaks two test files at once.** Both Feature files
  assert copy verbatim, deliberately with non-overlapping responsibilities
  (controller test = bands/copy/variants, `LandingPageTest` = reachability
  + help button). A band-3 pillar copy rewrite or a brand-initials change
  must land the view edit and both test edits in the same commit.
- **`harness:check-skills` reports an incomplete `landing` triad.**
  `scripts/check-skills.php` auto-discovers any `*-architecture` /
  `*-conventions` / `*-maintenance` directory and then demands all three
  for every discovered module prefix. The `landing` prefix exists, so
  shipping one or two of the three files fails the audit (exit 1) and
  `HarnessVerificationTest::test_artisan_check_skills_command_executes_successfully`
  with it. All three ship together, and any change to this screen's
  contract updates them in the same task (auto-update protocol).
- **A media-query rule in `_public-pages.scss` "does nothing".** Check the
  markup for a Bootstrap utility on the same element first: utilities
  compile with `!important` and beat the later normal declaration. This
  was real - `justify-content-between` on `.landing-footer-inner` left the
  <=904.98px centring rule dead until the utility was removed. The Dusk
  responsive method now asserts the computed value at every width. See
  `landing-conventions`.
- **Editing `_public-pages.scss` breaks the public certificate screen.**
  The file is shared with `public/certificates/show.blade.php`
  (`.ds-band-blue`, `.ds-hero-card`, `.max-w-reading`, `.icon-circle-*`).
  A "landing-only" tweak there moves the other public page too — run
  `--filter=PublicVerification` (or that module's test) alongside the
  landing filter.

## Skills Triad (This Module's Own Harness Requirement)

`.agents/skills/` must always hold the complete triple for this feature:

```bash
vendor/bin/sail artisan harness:check-skills
vendor/bin/sail artisan test --compact --filter=HarnessVerificationTest
```

Auto-update protocol: any code or schema change that impacts this module
re-writes the affected skills before the task finishes. Adding a band,
changing a token usage, changing the `dusk=` contract or the showcase
component list each belong in a specific skill — `landing-architecture`
(structure, tokens, static-page rule), `landing-conventions` (selectors,
components, colour, copy, routes), this one (tests and failure modes).
