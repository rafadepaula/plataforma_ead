---
name: landing-architecture
description: >
  Per-Organization public Landing Page domain (`GET /`, `landing.show`):
  `LandingPageController` resolves `organizations.landing_view` and
  renders the tenant's own blade `tenants.{landing_view}.landing`
  (`resources/views/tenants/{view}/landing.blade.php`; ligacerto,
  informatica); state-zero host or org without usable blade redirects to
  login; inactive org keeps landing viewable. Brand resolution via
  `OrgIdentityComposer`. Band/typography styling in shared
  `_public-pages.scss`. Use when designing or reviewing the public
  landing screen, before adding a tenant blade or reordering a band, or
  when deciding what a public marketing page is allowed to query.
license: MIT
metadata:
  feature: landing
  role: architecture
---

# Landing Page Architecture

## Overview

Public marketing page of each portal (`GET /`, route name `landing.show`)
— **per-Organization**. Each tenant's landing is its own Blade view,
named by `organizations.landing_view` and resolved to
`resources/views/tenants/{landing_view}/landing.blade.php`. Two exist
today: `tenants/ligacerto/landing.blade.php` and
`tenants/informatica/landing.blade.php`. The old single static
`resources/views/landing/show.blade.php` was removed from the
repository — never reference or resurrect it. Same URL serves anonymous
visitors and authenticated Admin/Gestor/Aluno; only the CTA targets
change.

## Controller Fallback Ladder (`LandingPageController::show()`)

```php
public function show(): View|RedirectResponse
{
    $context = OrgContext::current();

    if ($context->isStateZero()) {
        return redirect()->route('login');
    }

    $organization = $context->organization;
    $viewName = filled($organization->landing_view)
        ? 'tenants.'.$organization->landing_view.'.landing'
        : null;

    if ($viewName === null || ! ViewFacade::exists($viewName)) {
        return redirect()->route('login');
    }

    return view($viewName, ['organization' => $organization]);
}
```

Three rungs, in fixed order:

1. **State zero** (host matched no Organization — direct IP, unknown
   domain) → straight to the admin login. No tenant surface exists there
   at all.
2. **`landing_view` null or Blade missing** (`ViewFacade::exists()`
   check, so a stale `landing_view` value degrades gracefully) → the
   org's own login. An org without a published landing is not an error
   page, it is just a portal whose front door is the login.
3. Otherwise → render the tenant blade with `organization` injected. An
   **inactive** org still gets rung 3: `ResolveOrgFromHost` resolves it,
   `LandingPageController` never checks `status`, so the landing stays
   viewable while every auth surface behind it fails (asserted by
   `tests/Feature/Tenancy/LandingTest.php` and
   `tests/Feature/LandingPageControllerTest.php`). Do not "harden" this
   into a redirect — a suspended portal's public face staying up is
   deliberate.

## What The Controller Is Allowed To Query

One `Organization` read (already resolved by `OrgContext`) plus one view
existence check. No courses, no metrics, no certificate counts. Every
word on the page is copy inside the tenant blade; the only dynamic
expressions are the org's own fields (`name`, `logo_path`), `date('Y')`
and route lookups. Do not "improve" a tenant blade with real course
data: it couples a public page to tenant data and leaks catalog content
to anonymous visitors of a portal whose Gestor may not want it exposed.

## Tenant Blade Shape (4 Bands)

Both shipped blades share one skeleton, full-bleed under
`<x-layout.public :container="false" surface="white" class="landing-page">`:

| # | Band | Ground | Content |
| --- | --- | --- | --- |
| 1 | Header público | `--surface` | org logo (`landing-org-logo`) or `.brand-mark` fallback, `.landing-brand-name`, `<x-help-button key="landing" />`, theme toggle, `Entrar`/`Acessar plataforma` (`landing-login-link`) |
| 2 | Hero | `--blue-50` (`.ds-band-blue`) | `.tag`, `landing-title` `<h1>`, `.landing-lead`, guest CTA (`landing-hero-cta`) |
| 3 | Como funciona | `--surface` | `landing-section-title` `<h2>`, 3 `.ds-card` cards in Bootstrap `col-md-4` columns |
| 4 | Rodapé público | `--surface` | `© {ano} {org}`, `Validar certificado` → `route('certificates.verify')`, guest `Entrar` |

Copy is per-tenant and tenant-owned (LigaCerto: "Capacitação para ligas
esportivas..."; Informática+: "Cursos de informática..."), pinned
verbatim by `LandingPageTest`. Adding a tenant = new
`resources/views/tenants/{slug}/landing.blade.php` + setting
`organizations.landing_view`; no controller or route change.

## Brand In The Shell: `OrgIdentityComposer`

The brand every shell renders — topbar, sidebar drawer, guest panel and
`<title>` — resolves once in
`app/Http/View/Composers/OrgIdentityComposer.php`:

- Non-admin user (or guest) on a tenant host → host org's `name` +
  `logo_path`.
- Authenticated `role:admin` on **any** host (state zero or tenant
  alike) → global `system_name` setting, no logo — the platform staff is
  not "of" any portal.
- State zero (anyone) → `system_name`.

Views receive `orgBrand` (`name`, `logoPath`). Landing-specific brand in
the tenant blade reads `$organization` directly (header band), which is
why an Admin visiting a tenant landing sees the org's brand on the
landing itself but `system_name` in the authenticated shell.

## Width Tokens And Breakpoints (`_public-pages.scss`)

All landing selectors live in
`resources/scss/components/_public-pages.scss`, **shared** with the
public certificate-verification screen (`.ds-band-blue`, `.ds-hero-card`,
`.max-w-reading`, `.icon-circle-*` used by both) — a "landing-only" edit
there can move the other public screen.

Key tokens:

| Token | Where it applies |
| --- | --- |
| `--radius-2xl` (36px) | `.landing-hero` hero card — the screen's signature shape, pinned by the Dusk responsive test |
| `--content-max` (1240px) | `.landing-container`, `.landing-footer-inner` |
| `--reading-max` (760px) | `.landing-lead` |
| `--space-10`/`--space-9` | `.landing-hero` padding (shrinks under 576px) |

`.landing-band` is **full-bleed with token-driven gutters**, not a
centered container: `padding: var(--space-10)
max(var(--space-8), calc((100% - var(--content-max)) / 2))`. That is what
makes the alternation reach the viewport edges while the *content* still
stops at 1240px (`<x-layout.public>` is passed `:container="false"` for
exactly this reason). Below 905px the header drops to 64px and
`.landing-brand-name` is hidden (logo mark survives).

## Contextual Help

Standalone public document, no shared layout keying: each tenant blade
wires `<x-help-button key="landing" />` explicitly in its own header
band. See `help-architecture` for the standalone-document bucket and
`help-maintenance` for why a missing button there is a wiring omission
with no automatic enforcement.

## Related

- `landing-conventions` — `dusk=` contract, copy rules, footer link.
- `landing-maintenance` — tests and failure modes.
- `tenancy-architecture` — `OrgContext`, state zero, host resolution the
  controller leans on.
- `certificates-architecture` — the `certificates.verify` public route
  the footer links to.
