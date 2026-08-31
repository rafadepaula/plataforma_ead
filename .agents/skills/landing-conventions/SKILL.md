---
name: landing-conventions
description: >
  Code patterns and guardrails for the public Landing Page & Component
  Showcase: frozen `dusk=` contract (`landing-headline`,
  `landing-cta-login`, `landing-login-link` plus legacy `contact-button`;
  card/badge/progress/avatar wrappers never receive `dusk=`),
  showcase-must-be-real-components rule (`<x-ui.card>`, `<x-ui.badge>`,
  `<x-ui.progress>`, `<x-ui.avatar>`, `<x-ui.icon>`, `<x-ui.button>`; no
  stock photos, no one-off CSS), no-red/orange/yellow on this screen
  (mint for success and progress), pt-BR sentence case, and the footer
  link to the middleware-free `certificates.verify` route, the
  no-Bootstrap-utility-where-a-media-query-must-win rule on
  `.landing-footer-inner`, and the `HomePage` Dusk page object whose
  shortcuts must hold CSS `[dusk="..."]` values. Use when writing or
  editing `landing/show.blade.php`, adding a showcase card, writing a
  landing browser test, or touching any `dusk=` attribute on a public
  screen.
license: MIT
metadata:
  feature: landing
  role: conventions
  specs:
    - spec/specs/31-public-landing-page-and-showcase.md
    - spec/specs/11-landing-page-and-contextual-help-center.md
---

# Landing Page Conventions

## `dusk=` Contract Is Frozen

Exactly four selectors exist on this screen and only these may exist:

| Selector | Node | Note |
| --- | --- | --- |
| `landing-headline` | hero `<h1>` | exactly one |
| `landing-cta-login` | hero primary CTA | rendered twice in source (`@auth` / guest branch), one per response |
| `landing-login-link` | header button | same two-branch pattern |
| `contact-button` | band 6 contact CTA | **legacy** — predates the current spec, still pinned by the snapshot and by `LandingPageDuskTest`; do not rename, do not remove |

Rules:

- Keep `dusk="landing-login-link"` **on both branches** (guest `Entrar`
  and authenticated `Acessar plataforma`). Tests assert the selector, not
  the label — the authenticated branch intentionally renders a different
  string, so a test asserting the literal `Entrar` unconditionally is
  wrong, not the view.
- **Never put a new `dusk=` on a wrapper.** No
  `landing-showcase-card-*`, no per-badge, per-progress or per-avatar
  selector. `<x-ui.button>` forwards `dusk` to the rendered element via
  `$attributes->merge()`, which is precisely why it is easy to leak a
  fifth selector without noticing. Any addition fails
  `DuskSelectorContractTest` against the frozen 430-entry snapshot
  (see `landing-maintenance`).
- Need to target a showcase card in a test? Use its visible copy
  (`assertSee`) or the shared `.landing-*` class, not a new selector.

## Showcase Must Be Real Components, Not Pictures

Band 5 exists to prove the Design System. Every visual on it is built
from the system's own components:

```blade
<x-ui.card :border="false" elevation="sm" surface="white" class="landing-card landing-showcase-card h-100">
    ...
</x-ui.card>
```

- Allowed building blocks: `<x-ui.card>`, `<x-ui.badge>`,
  `<x-ui.progress>`, `<x-ui.avatar>`, `<x-ui.icon>`, `<x-ui.button>`.
- **No `<img>` stock photos, no placeholder PNGs, no external image URLs.**
  The course card uses the card's image slot as a pastel band with a
  floating status badge, not a photo.
- **No one-off CSS for a look a component already has.** Page-local
  classes (`.landing-*`) may position and size; they may not re-skin.
  If a needed look does not exist in the system, extend the component
  (new `variant`/`size`) instead of hand-rolling the styles here — a
  hand-rolled mint gradient on the landing page is invisible to every
  other screen and drifts on the next token change.
- Status copy on the showcase is staged and literal (`Em andamento`,
  `62%`, `nº 9f2b7c41`, `7 respostas`) — see `landing-architecture` for
  why it is never replaced with live queries.

## Colour: No Red, Orange or Yellow Anywhere on This Screen

The landing is a public first impression; nothing on it may read as
alarm. Success, progress and "conclusion" states use **mint**
(`--success-container` / `--on-success-container`, `variant="success"`
on badges and progress) — including the step-4 "done" circle in *Como
funciona*, which is mint while steps 1–3 stay `--primary-container`.

- Never reach for `variant="danger"`, `warning` or an orange accent here,
  not even for a decorative flourish.
- `--blue-50` bands + mint accents is the whole palette story of the
  page; a second accent colour is a redesign, not a tweak.

## Copy: pt-BR Sentence Case

Every user-visible string is pt-BR in **sentence case** — only the first
word and proper nouns capitalised (`Segurança do trabalho — NR 35`,
`Como funciona`, `Educação a distância`-style badge copy aside, keep
whatever the existing headline/badge strings are byte-identical, since
Feature tests assert them verbatim). Titles use `—` (em dash) as the
course/lesson separator. Body copy in cards ends without a period when it
is a fragment (`4 Módulos · 18 Aulas · 4 horas`), with one when it is a
sentence. Do not introduce marketing exclamation marks.

## Footer: Validation Link Points at the Hash Lookup Form

The footer's `Validar certificado` link must resolve, never be a `#`
stub, and must point at the **hash-less** public entry point:

```blade
<a href="{{ route('certificates.verify') }}" class="text-body-secondary text-decoration-none">Validar certificado</a>
```

`validar-certificado/{hash?}` takes an **optional** hash and is
registered **outside every `auth`/`guest`/`role` group — no middleware at
all** (see the route file comment and `certificates-architecture`): it
must resolve identically for an anonymous visitor and a logged-in Admin.
That is what makes it safe to link from a public marketing page, and why
the link must never be moved to an authenticated-only route.

Never embed a placeholder hash (a `str_repeat('0', 64)` or any other
literal) in this href: `PublicCertificateController::show()` resolves the
hash with `firstOrFail()`, so a hash that was never issued 404s and the
only public-validation entry point becomes unreachable. Without a hash
the same action renders `public/certificates/lookup.blade.php`, whose
`GET` form submits the typed hash back as `?hash=…`. The Feature test
pins this: `LandingPageControllerTest` asserts the href is exactly
`route('certificates.verify')` and that its path is exactly
`/validar-certificado` (no trailing segment), and
`PublicVerificationTest` covers the `?hash=` valid/revoked/unknown/blank
branches.

The remaining footer items (`Termos de uso`, `Privacidade`) have no page
yet and stay `href="#"`; `Suporte` is the in-page `#contato` anchor. Do
not invent routes that do not exist.

## Details the Feature Tests Pin Verbatim

These are contractual — changing them fails
`LandingPageControllerTest` and needs a deliberate test update:

- **Final step is mint.** The fourth "Como funciona" step carries
  `class="landing-step landing-step--success"` (the other three are bare
  `landing-step`); it is the page's only mint accent besides the badge
  system, and the Dusk test reads its computed colour to assert it
  differs from the preceding steps.
- **Contact button is tonal.**
  `<x-ui.button variant="tonal" href="mailto:…" dusk="contact-button">`
  inside `#contato` — not `filled`, not `ghost`; the blue band needs the
  tonal surface for contrast.
- **Certificate showcase card uses the `download` icon.**
  `<x-ui.button variant="ghost" size="sm" icon="download">` renders
  Lucide `download` (`lucide-download`, tray + arrow) from
  `components/ui/icon.blade.php`. Swapping the icon name changes the SVG
  path the test asserts.

## Layout Markup

- Full-bleed bands: `<section class="landing-band">`, plus
  `.ds-band-blue` when the band is on the blue ground (see
  `landing-architecture` alternation table).
- Grids: `.landing-grid` + `.landing-grid-3`/`.landing-grid-4` (keep the
  matching `ds-grid-3`/`ds-grid-4` class alongside — the Dusk responsive
  assertions read the shared class).
- Every card in a row gets `h-100` so equal-height alignment survives
  content drift.
- The view must keep `<x-help-button key="landing">` in the header band
  (`help-conventions`).

## Never Put a Bootstrap Utility Where a Media Query Must Win

`.landing-footer-inner` carries **only** `d-flex flex-wrap
align-items-center gap-3` — no `justify-content-between`. The desktop
distribution comes from the SCSS base rule
(`.landing-footer-inner { justify-content: space-between }`), because
Bootstrap compiles `justify-content-*` utilities with `!important`, which
beats the later normal declaration inside
`@media (max-width: 904.98px) { .landing-footer-inner { justify-content:
center } }` — the mobile rule was dead while the utility was on the div.
`LandingPageDuskTest::test_landing_page_responsive_contract_at_every_breakpoint`
now reads the computed `justify-content` at every width (`center` below
905px, `space-between` above), so re-adding the utility fails the suite.

The general rule for this screen: any property that a `_public-pages.scss`
media query overrides must be set in SCSS, never by a Bootstrap utility on
the same element. Utilities remain fine for properties no breakpoint
touches (`d-flex`, `gap-3`, `h-100`, spacing).

## Dusk Tests Go Through the `HomePage` Page Object

`tests/Browser/Pages/HomePage.php` owns the landing shortcuts; new browser
tests use `->visit('/')->on(new HomePage)->waitFor('@headline')` instead of
repeating literal selectors:

| Shortcut | Value |
| --- | --- |
| `@headline` | `[dusk="landing-headline"]` |
| `@ctaLogin` | `[dusk="landing-cta-login"]` |
| `@loginLink` | `[dusk="landing-login-link"]` |
| `@contact` | `#contato` |

Shortcut **values must be the CSS form** `[dusk="..."]`, never another
`@`-shortcut such as `'@headline' => '@landing-headline'`:
`ElementResolver::format()` does a single non-recursive `str_replace` and
only falls back to the `[dusk=…]` expansion when the selector came back
unchanged, so a chained shortcut reaches WebDriver as the literal invalid
selector `@landing-headline`. Adding a shortcut here adds **no** `dusk=`
attribute, so the frozen snapshot is unaffected — this is the sanctioned
way to make new browser tests readable.
