---
name: landing-maintenance
description: >
  Per-Organization public Landing Page (`GET /`, `landing.show`): tenant blades
  `tenants/{landing_view}/landing.blade.php`, controller redirect-vs-200 matrix
  (state zero, missing blade, inactive org), brand resolution via
  `OrgIdentityComposer`, per-blade `dusk=` contract, pt-BR copy pinned by Feature
  tests, band conventions from `_public-pages.scss`. Use when designing or reviewing
  the public landing screen, before adding a tenant blade or reordering a band, when
  writing/editing a tenant landing blade or touching `dusk=` on a public screen, or
  when `LandingPageControllerTest`, `LandingPageTest`, `Tenancy\LandingTest`,
  `Tenancy\ShellIdentityTest` or `LandingPageDuskTest` fails, a band renders
  unstyled, or a new blade/selector breaks the dusk-selector snapshot.
license: MIT
metadata:
  feature: landing
  roles: [architecture, conventions, maintenance]
---

# Per-Organization Landing Page (`landing-maintenance`)

Per-Organization public Landing Page (`GET /`, `landing.show`): `LandingPageController` resolves `organizations.landing_view` and renders the tenant blade `tenants.{landing_view}.landing`; state-zero or missing blade redirects to login; brand via `OrgIdentityComposer`; styling in `_public-pages.scss`.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing the public landing screen, before adding a tenant blade or reordering a band, or when deciding what a public marketing page is allowed to query. |
| `resource/conventions.md` | Writing or editing a tenant landing blade, adding a new tenant, or touching any `dusk=` attribute on a public screen. |
| `resource/maintenance.md` | `LandingPageControllerTest`, `LandingPageTest`, `Tenancy\LandingTest`, `Tenancy\ShellIdentityTest`, or `LandingPageDuskTest` fails; a landing band renders unstyled or at the wrong width; a new tenant blade or `dusk=` selector breaks the selector snapshot. |
