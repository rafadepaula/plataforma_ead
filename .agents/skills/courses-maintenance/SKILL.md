---
name: courses-maintenance
description: >
  Debug, test, edge-case guide for Courses/Modules/Lessons feature (SPEC-05):
  Blade views, `ModuleReorder.js` AJAX reorder module, mandatory PHPUnit/Dusk
  test files. Use when `MultiTenantCourseManagementTest`, `ModuleReorderTest`, or
  `LessonMultimediaTest` fail; drag-and-drop reorder not persisting; Lesson file
  upload land in wrong tenant folder; or YouTube embed preview show for URL that
  should be rejected.
license: MIT
metadata:
  feature: courses
  role: maintenance
  specs:
    - spec/specs/05-courses-modules-and-content-management.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Courses Maintenance

## Mandatory Test Coverage for This Module

These tests guard SPEC-05 contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/MultiTenantCourseManagementTest.php` — Gestor CRUD scoped to own
  org (`OrgScope` on `Course`); cross-tenant isolation (Gestor guessing other
  org's Course/Module ID get 404/403, never data); delete guard return 422 when
  Course have `active` enrollment (cancelled/completed enrollments do **not**
  block deletion); `role:aluno` forbidden from every
  `courses*`/`modules*`/`lessons*` route.
- `tests/Feature/ModuleReorderTest.php` — AJAX reorder endpoint persist dense
  `0..n-1` `order_index` sequence and reject any submitted id not belonging to
  route-bound parent (another course, or another org's course/module entirely).
- `tests/Feature/LessonMultimediaTest.php` — RF07's four content kinds (Rich
  Text/Imagem/PDF/YouTube), `FileUploadService` isolated storage path,
  `YoutubeSanitizerService` rejecting non-YouTube/XSS-style URLs.
- `tests/Unit/Services/YoutubeSanitizerServiceTest.php`,
  `tests/Unit/Services/FileUploadServiceTest.php` — service-level unit coverage,
  independent of HTTP layer.
- `tests/Browser/CourseManagementTest.php` — E2E: Gestor creates/edits/deletes
  Course, Module, and Lesson through UI.
- `tests/Browser/ModuleReorderTest.php` — E2E: reorder persist across full page
  reload.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=MultiTenantCourseManagementTest
vendor/bin/sail artisan test --filter=ModuleReorderTest
vendor/bin/sail artisan test --filter=LessonMultimediaTest
vendor/bin/sail dusk --filter=ModuleReorderTest
```

## `ModuleReorder.js` — Why Native Drag-and-Drop, Not jQuery

RF06 spec text literally say "Reordenação de módulos via AJAX/jQuery", but
jQuery/jQuery UI Sortable **not** existing dependency of this project
(`package.json` have none, CLAUDE.md forbid adding one without approval).
`ModuleReorder.js` instead bind browser native HTML5 drag-and-drop events
(`dragstart`/`dragover`/`drop`) directly on any `[data-reorder-url]` list — used
for both Module list (nested under Course) and Lesson list (nested under Module)
— and POST resulting `ordered_ids` array via `HttpClient.post()`:

```js
async persistOrder(list) {
    const url = list.getAttribute('data-reorder-url');
    const orderedIds = Array.from(list.querySelectorAll('[data-id]')).map((item) => Number(item.getAttribute('data-id')));

    try {
        await this.httpClient.post(url, { ordered_ids: orderedIds });
        this.notify('success', 'Ordem atualizada com sucesso.');
    } catch (error) {
        this.notify('error', `Falha ao reordenar: ${error.message}`);
    }
}
```

If jQuery/Sortable ever formally adopted as dependency later, replace
`bindList()` native listeners with `$(list).sortable({...})` calling same
`persistOrder(list)`. Do not change endpoint contract (`{ ordered_ids: [...] }`)
or `[data-reorder-url]`/`[data-id]` DOM contract Blade partials already rely on,
since `courses/modules/_list.blade.php` and `modules/lessons/index.blade.php`
both written against that exact shape.

Registered in `resources/js/app.js` same way `CsvImporter` is:

```js
window.ModuleReorder = new ModuleReorder(HttpClient, NotificationService);
document.addEventListener('DOMContentLoaded', () => window.ModuleReorder.init());
```

## Diagnosing "Reorder Doesn't Persist" / "Toast Never Shows"

- Confirm list element actually carry `data-reorder-url`. Set server-side from
  `route('modules.reorder', $course)` / `route('lessons.reorder', $module)`. If
  URL missing/empty, `persistOrder()` silently no-op (`if (!url ||
  orderedIds.length === 0) return;`). Check Blade partial, not JS, first.
- Every `<li>` must carry `data-id="{{ $model->id }}"`. `persistOrder()` derive
  `ordered_ids` purely from DOM order plus this attribute, so missing/duplicate
  `data-id` silently corrupt payload.
- Controller's own 422 ("não pertence a este curso/módulo") fire when *set* of
  submitted ids not match parent's children 1:1. If testing manually and see
  failure toast, check you not leave stale `<li>` in DOM from previous AJAX
  render.
- `HttpClient` throw on any non-2xx response and attach `.message` from JSON
  body. `ModuleReorder.notify('error', ...)` surface that message verbatim in
  toast, so read toast text before re-reading controller.

## Diagnosing Dusk Reorder Test That Times Out

Real native HTML5 drag-and-drop notoriously unreliable to emulate through
WebDriver (Selenium not dispatch genuine OS-level drag events).
`tests/Browser/ModuleReorderTest.php` do **not** attempt to simulate real drag:
it use `$browser->script()` to rearrange `<li>` DOM nodes directly, then call
`window.ModuleReorder.persistOrder(list)` — exact same function real `drop` event
would call — and assert order survive full page `->refresh()`. If you add new
reorder Dusk test, keep this pattern rather than trying to fire synthetic
`dragstart`/`drop` events; latter common source of flaky, unreproducible Dusk
failures in this codebase.

## Lesson Form: `type=quiz` Is Disabled Placeholder Only

`modules/lessons/_form.blade.php` `type` select offer `quiz` as option (per RF06
schema, `lessons.type` is `content|quiz`), but this feature's form only ever
expose four `content`-kind fields (Rich Text/Imagem/PDF/YouTube). SPEC-08 own
quiz question authoring. If asked to "wire up quiz creation", that out of scope
here; extend SPEC-08's own controller/views instead of adding fields to this
form.

## Client-Side YouTube Preview Best-Effort Only

Inline `<script>` in `modules/lessons/_form.blade.php` mirror
`YoutubeSanitizerService` regex client-side purely to render iframe preview. It
**not** security boundary. `StoreLessonRequest`/`UpdateLessonRequest`
`withValidator()` always re-validate through real `YoutubeSanitizerService`
server-side; if you ever see lesson with non-canonical `youtube_url` stored, bug
in controller/request, not preview script. Never trust client-side match result
for anything beyond visual preview.

## `FileUploadService` Path Mismatches

If Lesson's `image_path`/`pdf_path` not start with
`orgs/{course.org_id}/courses/{course.id}/...`, check
`LessonController::handleMediaFields()` pass `$module->course` (Lesson's actual
parent Course), not `auth()->user()`'s own org. See `courses-conventions` for why
service resolve tenant from Course model, not session.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change to
`CourseController`/`ModuleController`/`LessonController`, the
`courses*`/`modules*`/`lessons*` routes, Blade views under
`resources/views/courses/` plus `resources/views/modules/lessons/`, or
`ModuleReorder.js` **must** update all three courses skills
(`courses-architecture`, `courses-conventions`, `courses-maintenance`) in same
change, before task considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affect what reviewer must check
  for this module.
- Run `vendor/bin/sail artisan harness:check-skills`. It fail build if any of
  three `courses-*` skills missing.

## Related Specs

- `spec/specs/05-courses-modules-and-content-management.md` — RF06, RF07.
- `auth-orgs-maintenance` — analogous module this one mirror (CSV import chunking
  vs this module's AJAX reorder).
- `tenancy-maintenance` — underlying `OrgScope` contract this module build on.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle chain)** —
one method drive create, edit, state change, delete, consequence — **not** by
module, spec, or use case. Consequences when maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as numbered
  steps inside chain method, possibly in file named after another module when
  journey cross module boundaries. Locate them with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name.
  Missing per-module file **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new numbered
  step carrying own UI **and** DB assertion. Create new method only for
  independent negatives (403, cross-tenant, other actor); create new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario. Match
  line to its `// N.` comment. Late failure usually mean earlier step not persist
  what it should.
- **Database**: no DB trait declared in `tests/Browser/*`; `DatabaseTruncation`
  inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` is
  suite-wide performance regression. Files, cache and session **not** reset
  between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
