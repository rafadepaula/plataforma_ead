---
name: landing-architecture
description: >
  Public Landing Page & Component Showcase domain: `landing/show.blade.php`
  rendered as 7 full-bleed bands alternating `--blue-50` and `--surface`,
  36px (`--radius-2xl`) hero card on a 76px (`--appbar-height`) public
  header, `--content-max`=1240px band container against a 760px
  (`--reading-max`) lead column, and why the page is static by design —
  zero Eloquent queries, thin `LandingPageController::show()` returning
  `view('landing.show')`, all role branching done in Blade. Use when
  designing or reviewing the public landing/showcase screen, before
  adding or reordering a band, or when deciding what a public marketing
  page is allowed to query.
license: MIT
metadata:
  feature: landing
  role: architecture
---

# Landing Page Architecture

## Overview

Public marketing page (`GET /`, route name `landing.show`) plus a
**component showcase**: the band that proves the Design System to an
anonymous visitor by rendering real `<x-ui.*>` components — an in-progress
course card, an issued-certificate card and a forum-question card —
instead of stock photos. Same URL serves anonymous visitors and
authenticated Admin/Gestor/Aluno; the only thing that changes is where
the two CTAs point.

## Static by Design: Zero Eloquent Queries

`App\Http\Controllers\LandingPageController` is deliberately one line
thick:

```php
public function show(): View
{
    return view('landing.show');
}
```

No model, no repository, no `Organization` resolution, no metrics query.
Every word on the page is literal copy inside
`resources/views/landing/show.blade.php`; the only dynamic expressions are
`config('app.name')`, `date('Y')` and route lookups. Consequences:

- **No tenant resolution happens server-side.** `OrgScope` never fires,
  so no `UnresolvedOrgContextException` can ever come out of this route
  (contrast with `tenancy-architecture`): an anonymous visitor has no org
  and the page needs none.
- **Do not "improve" it with real data.** Reading the newest course or the
  real certificate count here would couple a public, uncacheable-in-tests
  marketing page to tenant data, and leak cross-org content to anonymous
  visitors. The showcase is *staged copy that looks like real components*,
  not a query.
- **Authenticated branching lives in the view**, not the controller. A
  `@php` block derives `$dashboardRoute` (`student.courses.index` for
  `role:aluno`, `admin.dashboard` for the staff roles) and `@auth`/`@else`
  swaps the CTA target. Adding a third role destination means editing that
  block, never the controller.
- **Feature tests are render assertions**, not data assertions — no
  factory seeding is needed to render this page.

## 7-Band Alternation

The view renders as `<x-layout.public :container="false"
surface="white" class="landing-page">`: the layout emits the
`<!doctype html>`/`@vite` boilerplate and the `footer` slot, the view
owns every band full-bleed. Bands alternate `--blue-50` (#f2f6ff) and
`--surface` (white) so no two neighbours share a ground:

| # | Band | Ground | Content |
| --- | --- | --- | --- |
| 1 | Header público | `--surface` | 76px bar, brand mark, `Entrar`/`Acessar plataforma` |
| 2 | Hero | `--blue-50` (`.ds-band-blue`) | badge, 44px headline, lead, primary CTA |
| 3 | Capacidades / 3 pilares | `--surface` | 3 cards |
| 4 | Como funciona / 4 passos | `--blue-50` (`.ds-band-blue`) | 4 numbered circles |
| 5 | Vitrine de componentes | `--surface` (`.landing-showcase-band`) | 3 showcase cards |
| 6 | Contato institucional | `--blue-50` (`.ds-band-blue`) | `#contato` anchor, CTA |
| 7 | Rodapé público | `--surface` | copyright + validation link |

Rules that keep it legible:

- Ground colour is **one class per section** — `.ds-band-blue` for the
  blue bands, nothing (inheriting `.landing-page { background: var(--surface) }`)
  for the white ones. Band 5 re-states `var(--surface)` explicitly
  (`.landing-showcase-band`) so the showcase stays white even if someone
  later gives `.landing-band` a tint.
- Inserting a band means re-deriving the alternation, not just appending a
  `<section>`. Two blue bands in a row read as one broken band.
- Band 6 carries `id="contato"` and is the target of both the in-page
  `Suporte` footer link and the anchor contract.

## Width and Radius Tokens

| Token | Value | Where it applies |
| --- | --- | --- |
| `--appbar-height` | 76px | `.landing-header` (drops to 64px under 905px) |
| `--radius-2xl` | 36px | `.landing-hero` and `.ds-hero-card` |
| `--content-max` | 1240px | `.landing-container`, `.landing-footer-inner`, `.landing-band` inline padding |
| `--reading-max` | 760px | `.landing-lead`, `.landing-reading-width` (band 6 text) |
| `--space-10` | 64px | `.landing-hero` vertical padding |

`.landing-band` is **full-bleed with token-driven gutters**, not a
centered container: `padding: var(--space-10)
max(var(--space-8), calc((100% - var(--content-max)) / 2))`. That is what
makes the alternation reach the viewport edges while the *content* still
stops at 1240px. Do not replace it with Bootstrap `.container` — that
would re-centre a narrower box and break the full-bleed band look
(`<x-layout.public>` is passed `:container="false"` for exactly this
reason).

The two maxima are intentionally different: **1240px for grids and the
footer** (card rows need the width), **760px for prose** (`.landing-lead`
and band 6 copy). A long paragraph set inside a 1240px band container
without the 760px cap is a regression.

Hero card: `background: var(--blue-50)` on a blue band — same hue, so the
36px radius is the only thing delineating it; padding is
`var(--space-10) var(--space-9)` (64px/48px), shrinking under 576px. Keep
the radius on the hero when restyling; the 36px hero is the screen's
signature shape.

## Breakpoints (`_public-pages.scss`)

- **≤1239.98px** — band gutters collapse to `--space-8`; the 4-step grid
  (`landing-grid-4`/`ds-grid-4`) goes 4 → 2 columns.
- **≤904.98px** — everything goes single column (`grid-3` and `grid-4`
  alike), header to 64px, `.landing-brand-name` hidden (mark survives),
  footer content centres, hero headline drops `--font-size-display`
  (44px) → `--font-size-h1` (36px).

All landing selectors live in `resources/scss/components/_public-pages.scss`
shared with the public certificate-verification screen (`.ds-band-blue`,
`.ds-hero-card`, `.max-w-reading` are used by both) — a "landing-only"
edit there can move the other public screen.

## Contextual Help

Standalone public document, no shared layout keying: the view must wire
`<x-help-button key="landing">` explicitly in its own header band. See
`help-architecture` for the standalone-document bucket and
`help-maintenance` for why a missing button there is a wiring omission
with no automatic enforcement.
