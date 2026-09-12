---
name: landing-conventions
description: >
  Code patterns and guardrails for the per-Organization public Landing
  Page (`tenants/{landing_view}/landing.blade.php`): per-blade `dusk=`
  contract (`landing-org-logo`, `landing-login-link`, `landing-hero-cta`;
  `organization-landing-view` on the org admin form), footer link to the
  middleware-free host-scoped `certificates.verify` route, pt-BR copy
  pinned verbatim by Feature tests, band/ground conventions from
  `_public-pages.scss`. Use when
  writing or editing a tenant landing blade, adding a new tenant, or
  touching any `dusk=` attribute on a public screen.
license: MIT
metadata:
  feature: landing
  role: conventions
---

# Landing Page Conventions

## `dusk=` Contract Per Tenant Blade

Each `resources/views/tenants/{view}/landing.blade.php` carries exactly
these selectors, and no others:

| Selector | Node | Note |
| --- | --- | --- |
| `landing-org-logo` | header `<img>` (only when `$organization->logo_path` filled) | absent when logo missing and `.brand-mark` fallback renders |
| `landing-login-link` | header button, rendered twice in source (`@auth` / `@else` branch) | one per response; authenticated branch points at `$dashboardRoute`, guest branch at `route('login')` |
| `landing-hero-cta` | hero primary CTA, `@guest` branch only | never rendered for authenticated users |

Plus one admin-form selector outside the blades:
`organization-landing-view` in `resources/views/components/organizations/_form.blade.php`
(the `landing_view` picker). The legacy selectors
`landing-headline`, `landing-cta-login` and `contact-button` no longer
exist in any view — a test or page-object still referencing them is
stale, not the views.

Rules:

- **Never put a new `dusk=` on a wrapper.** No per-card, per-band or
  per-footer selector. `<x-ui.button>` forwards `dusk` to the rendered
  element via `$attributes->merge()`, which is precisely why it is easy
  to leak a fifth selector without noticing. Any addition fails
  `tests/Feature/Theme/DuskSelectorContractTest.php` against the frozen
  snapshot (see `landing-maintenance`).
- Need to target a "Como funciona" card in a test? Use its visible copy
  (`assertSee`) or the shared `.ds-card`/`.col-md-4` classes — the Dusk
  responsive test reads the card ratio through `.landing-band .col-md-4`,
  not through any `dusk=`.

## Footer: Validation Link Points At The Hash Lookup Form

Every tenant blade's footer must keep the `Validar certificado` link
resolving to the **hash-less** public entry point:

```blade
<a href="{{ route('certificates.verify') }}" class="text-body-secondary">Validar certificado</a>
```

`validar-certificado/{hash?}` takes an **optional** hash and is
registered **outside every `auth`/`guest`/`role` group — no middleware at
all** (see `certificates-architecture`). It is public but **host-scoped**:
a hash verifies only on its issuing org's portal, wrong host 404s. That
is what makes it safe to link from a public marketing page, and why the
link must never be moved to an authenticated-only route.

Never embed a placeholder hash in this href: a hash that was never issued
404s and the only public-validation entry point becomes unreachable.
Without a hash the same action renders `public/certificates/lookup.blade.php`,
whose `GET` form submits the typed hash back as `?hash=…`.
`LandingPageControllerTest` pins the href: guest and aluno CTAs carry
`href="route('login')"`/`href="route('student.courses.index')"`, staff CTAs
carry `href="route('admin.dashboard')"`, and the footer carries
`href="route('certificates.verify')"` — all asserted as literal
`href="..."` strings.

## CTA Routing Contract

The blade derives `$dashboardRoute` in one `@php` block: authenticated
`role:aluno` → `student.courses.index` (fallback `url('/')` when route
missing), any other authenticated role → `admin.dashboard`, guest →
`null`. Header button swaps `Acessar plataforma` (`@auth`) / `Entrar`
(`@else`); hero CTA renders `@guest` only. Adding a third role
destination means editing that block, never the controller.

## Copy: pt-BR, Tenant-Owned, Pinned Verbatim

Every user-visible string is pt-BR in sentence case. Copy is
**per-tenant by design** — each blade owns its badge, headline and lead
(`LigaCerto — Treinamentos Esportivos` /
`Capacitação para ligas esportivas com certificado em dia`;
`Informática+ — Formação em TI` / `Cursos de informática com certificado
reconhecido`). `LandingPageTest` and `LandingPageDuskTest` assert key
strings verbatim, so a copy rewrite must land the view edit and the test
edits in the same task. Titles use `—` (em dash) as org/branding
separator. No marketing exclamation marks.

## Band Markup And Ground

- Full-bleed bands: `<section class="landing-band">`; the hero band
  adds `.landing-hero-band` + `.ds-band-blue`, the "Como funciona" band
  stays on plain surface. Bands must alternate — two blue bands in a row
  read as one broken band (asserted by the Dusk bands test reading
  computed `backgroundColor`).
- Cards: `.ds-card p-4 h-100` in Bootstrap `col-md-4` columns; `h-100`
  keeps equal-height alignment.
- Brand: org logo `<img class="brand-logo" dusk="landing-org-logo">`
  when `logo_path` set, `.brand-mark` fallback otherwise;
  `.landing-brand-name` carries the org name.
- No tenant blade mounts a `<x-help-button>` — the landing page
  deliberately has no contextual help mapping (see `help-conventions`).

## Layout Files

- Blades: `resources/views/tenants/{landing_view}/landing.blade.php`
  only. The old `resources/views/landing/show.blade.php` was removed —
  do not recreate or reference it.
- Shell: `<x-layout.public :container="false" surface="white"
  class="landing-page">` — `:container="false"` because `.landing-band`
  is full-bleed with token-driven gutters (see `landing-architecture`).
- Styles: shared `resources/scss/components/_public-pages.scss` (also
  styles the public certificate screens). Page-local `.landing-*`
  classes may position and size; they may not re-skin a look a component
  already has.
- Any property a `_public-pages.scss` media query overrides must be set
  in SCSS, never by a Bootstrap `!important` utility on the same
  element. Utilities stay fine for properties no breakpoint touches
  (`d-flex`, `gap-3`, `h-100`).

## Browser Tests Drive The Blade Directly

`LandingPageDuskTest` visits `/` on the Dusk tenant host (ligacerto
blade) and uses the `.landing-hero` class plus the raw `@`-selectors
`@landing-login-link` / `@landing-hero-cta` (Dusk auto-expands `@name`
to `[dusk="name"]`). The legacy `tests/Browser/Pages/HomePage.php` page
object (old shortcuts `@headline`/`@ctaLogin`/`@contact`) was removed —
build new tests on the live selectors above, never on the retired ones.
