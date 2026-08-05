---
name: help-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Landing Page &
  Contextual Help Center feature (SPEC-11): the `<x-help-button key="...">`
  wiring convention, `HelpArticleFactory`'s `global()`/`forOrg()` states,
  the `withoutEvents()` factory workaround for Admin-created global
  articles, the `dusk="help-button-{key}"` / `dusk="help-article-content-{key}"`
  test-selector contract, and the inert-vs-populated Blade branch. Use
  whenever writing a Blade view, layout, controller, or JS that touches
  `HelpArticle` records or mounts a new `<x-help-button>` on a screen.
license: MIT
metadata:
  feature: help
  role: conventions
  specs:
    - spec/specs/11-landing-page-and-contextual-help-center.md
---

# Help Center Conventions

## Mounting `<x-help-button>` on a New Screen

Staff and guest screens get coverage for free from their shared layout
(see `help-architecture`'s coverage table) — do **not** add a second
`<x-help-button>` inside an individual `layouts.app`/`layouts.guest` view,
that would render two buttons keyed to the same route.

A new fully-public/standalone document (no shared layout) must add the
component explicitly with a literal, stable `key` string (not derived
from the route name, since these documents are often reachable by more
than one URL pattern or none at all):

```blade
<x-help-button key="certificates.verify" />
```

Pick a `target_page_key` that mirrors the screen's route name when one
exists (`courses.index`, `student.courses.index`) for staff/guest
screens — this is what `Route::currentRouteName()` will pass at runtime,
so the key used when seeding/authoring a `HelpArticle` must match it
exactly, including the full dotted route name.

## `HelpArticleFactory`: `global()` vs. `forOrg()`

```php
// Global article (org_id = null) — the natural default, no state needed:
HelpArticle::factory()->create(['target_page_key' => 'courses.index']);
HelpArticle::factory()->global()->create([...]); // same result, explicit

// Org-specific override:
$org = Organization::factory()->create();
HelpArticle::factory()->forOrg($org)->create(['target_page_key' => 'courses.index']);
```

`global()` and the bare factory produce the same row shape — prefer
`global()` explicitly in tests that exercise fallback behavior
(`ContextualHelpFallbackTest`-style), where the contrast with `forOrg()`
matters to the reader; the bare factory is fine when the test's focus is
elsewhere and `org_id` is incidental.

## Creating a Global Article While Acting as an Admin: `withoutEvents()`

`OrgScope`'s `creating` hook stamps `org_id` from
`session('active_org_id')` (or throws `UnresolvedOrgContextException` if
neither the user nor session resolves one — see `tenancy-conventions`).
A test that wants a **global** `HelpArticle` (`org_id = null`) seeded
independently of whatever Admin session is active must bypass that hook:

```php
HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
    'target_page_key' => 'organizations.index',
    'title' => 'Como gerenciar organizações',
    'content' => 'Conteúdo de ajuda para o Admin.',
]));
```

Without `withoutEvents()`, the `creating` hook would silently overwrite
the factory's `org_id => null` with the acting Admin's
`session('active_org_id')`, turning what the test intended as a global
article into an org-specific one (or throwing, if no org is
impersonated) — this is the same workaround documented for
`ForumTopic::withoutEvents()` in `forum-conventions`, applied here to
`HelpArticle`.

## Blade Contract: Populated vs. Inert Branch

`components/help-button.blade.php` branches on whether
`HelpButton::$article` resolved:

```blade
@if($article)
    <button ... data-modal-target="{{ $modalId }}" dusk="help-button-{{ $key }}">...</button>
    <x-ui.modal id="{{ $modalId }}" title="{{ $article->title }}" size="md">
        <div dusk="help-article-content-{{ $key }}">{{ $article->content }}</div>
    </x-ui.modal>
@else
    <button ... disabled dusk="help-button-{{ $key }}">...</button>
@endif
```

Both branches always render the same `dusk="help-button-{key}"`
attribute on the trigger `<button>` — a test asserting the button exists
does not need to know in advance whether an article was authored.
`dusk="help-article-content-{key}"` only exists inside the populated
branch's modal. Never add a third branch (e.g. a loading state) without
updating both `HelpCenterTest` (renders-populated / renders-inert cases)
and `HelpCenterDuskTest`.

`$modalId` is `'help-modal-'.str($key)->slug()` — a route name like
`student.courses.index` slugifies predictably; do not hand-roll a
different modal id scheme for a new screen, reuse the component's own
derivation.

## Modal Open/Close Reuses `window.ModalManager`, No Alpine.js

The help modal opens via the same `data-modal-target="{{ $modalId }}"` /
`data-modal-dismiss="true"` attribute pair that `window.ModalManager`
(registered once in `app.js`, see `ModalManager.js`'s own docblock)
already binds globally. This project has **no Alpine.js** — `x-ui.modal`'s
backdrop ships with a static inline `display: flex` and is hidden on load
by `ModalManager.hideBackdropsOnLoad()` (a plain
`DOMContentLoaded` listener), not by any `x-show`/`x-cloak` directive.
Never write a second modal-open/close implementation for a new help
button; never attribute backdrop-visibility behavior to Alpine in a new
comment or test — see `help-maintenance` for the Dusk implication.

## `HelpArticleResolverService` Is the Only Resolution Path

Never query `HelpArticle` directly from a controller or Blade view to
decide what to show for a `target_page_key` — always go through
`app(HelpArticleResolverService::class)->resolve(...)`, as
`App\View\Components\HelpButton` does. This keeps the
org-specific-then-global fallback logic in one place; a second ad-hoc
query elsewhere would drift the moment the fallback rule changes.
