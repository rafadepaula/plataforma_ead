---
name: help-conventions
description: >
  Code patterns, snippets, guardrails for Landing Page & Contextual Help
  Center feature: `<x-help-button key="...">` wiring convention,
  `HelpArticleFactory` `global()`/`forOrg()` states, `withoutEvents()`
  factory workaround for Admin-created global articles,
  `dusk="help-button-{key}"` / `dusk="help-article-content-{key}"`
  test-selector contract, inert-vs-populated Blade branch. Use when
  writing Blade view, layout, controller, or JS touching `HelpArticle`
  records or mounting new `<x-help-button>` on screen.
license: MIT
metadata:
  feature: help
  role: conventions
---

# Help Center Conventions

## Mounting `<x-help-button>` on New Screen

Staff and guest screens get coverage free from shared layout (see
`help-architecture` coverage table). Do **not** add second
`<x-help-button>` inside individual `layouts.app`/`layouts.guest` view —
that render two buttons keyed to same route.

New fully-public/standalone document (no shared layout) must add
component explicitly with literal, stable `key` string (not derived from
route name, since these documents often reachable by more than one URL
pattern or none at all):

```blade
<x-help-button key="certificates.verify" />
```

Pick `target_page_key` mirroring screen route name when one exist
(`courses.index`, `student.courses.index`) for staff/guest screens. That
is what `Route::currentRouteName()` pass at runtime, so key used when
seeding/authoring `HelpArticle` must match exactly, including full dotted
route name.

## `HelpArticleFactory`: `global()` vs `forOrg()`

```php
// Global article (org_id = null) — the natural default, no state needed:
HelpArticle::factory()->create(['target_page_key' => 'courses.index']);
HelpArticle::factory()->global()->create([...]); // same result, explicit

// Org-specific override:
$org = Organization::factory()->create();
HelpArticle::factory()->forOrg($org)->create(['target_page_key' => 'courses.index']);
```

`global()` and bare factory produce same row shape. Prefer `global()`
explicitly in tests exercising fallback behavior
(`ContextualHelpFallbackTest`-style), where contrast with `forOrg()`
matter to reader. Bare factory fine when test focus elsewhere and
`org_id` incidental.

## Creating Global Article While Acting as Admin: `withoutEvents()`

`OrgScope` `creating` hook stamp `org_id` from
`session('active_org_id')` (or throw `UnresolvedOrgContextException` if
neither user nor session resolve one — see `tenancy-conventions`). Test
wanting **global** `HelpArticle` (`org_id = null`) seeded independent of
whatever Admin session active must bypass that hook:

```php
HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
    'target_page_key' => 'organizations.index',
    'title' => 'Como gerenciar organizações',
    'content' => 'Conteúdo de ajuda para o Admin.',
]));
```

Without `withoutEvents()`, `creating` hook silently overwrite factory
`org_id => null` with acting Admin `session('active_org_id')`, turning
intended global article into org-specific one (or throwing, if no org
impersonated). Same workaround documented for
`ForumTopic::withoutEvents()` in `forum-conventions`, applied here to
`HelpArticle`.

## Blade Contract: Populated vs Inert Branch

`components/help-button.blade.php` branch on whether
`HelpButton::$article` resolved:

```blade
@if($article)
    <button ... data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" dusk="help-button-{{ $key }}">...</button>
    <x-ui.modal id="{{ $modalId }}" title="{{ $article->title }}" size="md">
        <div dusk="help-article-content-{{ $key }}">{{ $article->content }}</div>
    </x-ui.modal>
@else
    <button ... disabled dusk="help-button-{{ $key }}">...</button>
@endif
```

Both branches always render same `dusk="help-button-{key}"` attribute on
trigger `<button>` — test asserting button exist do not need to know in
advance whether article authored. `dusk="help-article-content-{key}"`
exist only inside populated branch modal. Never add third branch (e.g.
loading state) without updating both `HelpCenterTest`
(renders-populated / renders-inert cases) and `HelpCenterDuskTest`.

`$modalId` is `'help-modal-'.str($key)->slug()` — route name like
`student.courses.index` slugify predictably. Do not hand-roll different
modal id scheme for new screen. Reuse component own derivation.

## Modal Open/Close Is Declarative `bootstrap.Modal`, No Alpine.js, No `ModalManager`

Help modal open through `data-bs-toggle="modal"` +
`data-bs-target="#{{ $modalId }}"` on the trigger and close through
`data-bs-dismiss="modal"` — pure `bootstrap.Modal`, no project JS at all.
The hand-rolled `ModalManager` was deleted in the Bootstrap 5.3
migration; do not reintroduce it or any `data-modal-target` pair. This
project also have **no Alpine.js**. Never write second
modal-open/close implementation for new help button. Never attribute
backdrop-visibility behavior to Alpine in new comment or test — see
`help-maintenance` for Dusk implication.

## `HelpArticleResolverService` Is Only Resolution Path

Never query `HelpArticle` directly from controller or Blade view to
decide what to show for `target_page_key`. Always go through
`app(HelpArticleResolverService::class)->resolve(...)`, as
`App\View\Components\HelpButton` do. Keep org-specific-then-global
fallback logic in one place. Second ad-hoc query elsewhere drift moment
fallback rule change.
