---
name: help-maintenance
description: >
  Contextual Help Center: `help_articles` schema (org-scoped, nullable `org_id` =
  global), `HelpArticleResolverService` org-then-global fallback, `<x-help-button>`
  wiring outside `OrgScope`, factory `global()`/`forOrg()` states, `dusk` selector
  contract, no-Alpine `bootstrap.Modal` Dusk gotcha. Use when designing or reviewing
  features touching `HelpArticle` data, before adding a new `target_page_key`, when
  deciding how a new screen wires contextual help, when writing Blade/controller/JS
  that mounts `<x-help-button>`, or when `LandingPageTest`, `HelpCenterTest`,
  `ContextualHelpFallbackTest` or `HelpCenterDuskTest` fails, the help button does
  not render, or the wrong article (org vs global) resolves.
license: MIT
metadata:
  feature: help
  roles: [architecture, conventions, maintenance]
---

# Contextual Help Center (`help-maintenance`)

Contextual Help Center: `help_articles` schema (directly org-scoped, nullable `org_id` means global), `HelpArticleResolverService` org-specific-then-global fallback, `<x-help-button>` wiring, and the 100%-of-screens coverage requirement.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching `HelpArticle` data, before adding a new `target_page_key`, or when deciding how a new screen (staff, guest, fully public) wires in contextual help. |
| `resource/conventions.md` | Writing a Blade view, layout, controller, or JS touching `HelpArticle` records or mounting a new `<x-help-button>` on a screen. |
| `resource/maintenance.md` | `LandingPageTest`, `HelpCenterTest`, or `ContextualHelpFallbackTest` fails; help button does not render on a new screen; wrong article (org-specific vs global) resolves; `HelpCenterDuskTest` flake opening the modal. |
