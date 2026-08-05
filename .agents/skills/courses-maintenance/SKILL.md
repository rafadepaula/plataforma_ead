---
name: courses-maintenance
description: >
  Debugging, testing, and edge-case guide for the Courses/Modules/Lessons
  feature (SPEC-05): the Blade views, the `ModuleReorder.js` AJAX reorder
  module, and the mandatory PHPUnit/Dusk test files. Use when
  `MultiTenantCourseManagementTest`, `ModuleReorderTest`, or
  `LessonMultimediaTest` is failing; a drag-and-drop reorder isn't
  persisting; a Lesson file upload lands in the wrong tenant folder; or a
  YouTube embed preview shows for a URL that should have been rejected.
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

These tests guard the SPEC-05 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/MultiTenantCourseManagementTest.php` — Gestor CRUD scoped
  to their own org (`OrgScope` on `Course`); cross-tenant isolation (a
  Gestor guessing another org's Course/Module ID gets 404/403, never data);
  the delete guard returns 422 when the Course has an `active` enrollment
  (cancelled/completed enrollments do **not** block deletion); `role:aluno`
  is forbidden from every `courses*`/`modules*`/`lessons*` route.
- `tests/Feature/ModuleReorderTest.php` — the AJAX reorder endpoint
  persists a dense `0..n-1` `order_index` sequence and rejects any
  submitted id that doesn't belong to the route-bound parent (another
  course, or another org's course/module entirely).
- `tests/Feature/LessonMultimediaTest.php` — RF07's four content kinds
  (Rich Text/Imagem/PDF/YouTube), `FileUploadService`'s isolated storage
  path, `YoutubeSanitizerService` rejecting non-YouTube/XSS-style URLs.
- `tests/Unit/Services/YoutubeSanitizerServiceTest.php`,
  `tests/Unit/Services/FileUploadServiceTest.php` — service-level unit
  coverage, independent of the HTTP layer.
- `tests/Browser/CourseManagementTest.php` — E2E: Gestor creates/edits/
  deletes a Course, Module, and Lesson through the UI.
- `tests/Browser/ModuleReorderTest.php` — E2E: reorder persists across a
  full page reload.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=MultiTenantCourseManagementTest
vendor/bin/sail artisan test --filter=ModuleReorderTest
vendor/bin/sail artisan test --filter=LessonMultimediaTest
vendor/bin/sail dusk --filter=ModuleReorderTest
```

## `ModuleReorder.js` — Why It's Native Drag-and-Drop, Not jQuery

RF06's spec text literally says "Reordenação de módulos via AJAX/jQuery",
but jQuery/jQuery UI Sortable is **not** an existing dependency of this
project (`package.json` has none, and CLAUDE.md forbids adding one without
approval). `ModuleReorder.js` instead binds the browser's native HTML5
drag-and-drop events (`dragstart`/`dragover`/`drop`) directly on any
`[data-reorder-url]` list — used for both the Module list (nested under a
Course) and the Lesson list (nested under a Module) — and POSTs the
resulting `ordered_ids` array via `HttpClient.post()`:

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

If jQuery/Sortable is ever formally adopted as a dependency later, replace
`bindList()`'s native listeners with `$(list).sortable({...})` calling the
same `persistOrder(list)` — do not change the endpoint contract
(`{ ordered_ids: [...] }`) or the `[data-reorder-url]`/`[data-id]` DOM
contract the Blade partials already rely on, since `courses/modules/_list
.blade.php` and `modules/lessons/index.blade.php` are both written against
that exact shape.

Registered in `resources/js/app.js` the same way `CsvImporter` is:

```js
window.ModuleReorder = new ModuleReorder(HttpClient, NotificationService);
document.addEventListener('DOMContentLoaded', () => window.ModuleReorder.init());
```

## Diagnosing "Reorder Doesn't Persist" / "Toast Never Shows"

- Confirm the list element actually carries `data-reorder-url` — it's set
  server-side from `route('modules.reorder', $course)` /
  `route('lessons.reorder', $module)`. If the URL is missing/empty,
  `persistOrder()` silently no-ops (`if (!url || orderedIds.length === 0)
  return;`) — check the Blade partial, not the JS, first.
- Every `<li>` must carry `data-id="{{ $model->id }}"` — `persistOrder()`
  derives `ordered_ids` purely from DOM order + this attribute, so a
  missing/duplicate `data-id` silently corrupts the payload.
- The controller's own 422 ("não pertence a este curso/módulo") fires when
  the *set* of submitted ids doesn't match the parent's children 1:1 — if
  you're testing manually and see a failure toast, check you didn't leave a
  stale `<li>` in the DOM from a previous AJAX render.
- `HttpClient` throws on any non-2xx response and attaches `.message` from
  the JSON body — `ModuleReorder.notify('error', ...)` surfaces that
  message verbatim in the toast, so read the toast text before re-reading
  the controller.

## Diagnosing a Dusk Reorder Test That Times Out

Real native HTML5 drag-and-drop is notoriously unreliable to emulate
through WebDriver (Selenium doesn't dispatch genuine OS-level drag events).
`tests/Browser/ModuleReorderTest.php` does **not** attempt to simulate a
real drag: it uses `$browser->script()` to rearrange the `<li>` DOM nodes
directly, then calls `window.ModuleReorder.persistOrder(list)` — the exact
same function a real `drop` event would call — and asserts the order
survived a full page `->refresh()`. If you add a new reorder Dusk test,
keep this pattern rather than trying to fire synthetic `dragstart`/`drop`
events; the latter is a common source of flaky, unreproducible Dusk
failures in this codebase.

## Lesson Form: `type=quiz` Is a Disabled Placeholder Only

`modules/lessons/_form.blade.php`'s `type` select offers `quiz` as an
option (per RF06's schema, `lessons.type` is `content|quiz`), but this
feature's form only ever exposes the four `content`-kind fields (Rich
Text/Imagem/PDF/YouTube) — SPEC-08 owns quiz question authoring. If you're
asked to "wire up quiz creation," that is out of scope here; extend
SPEC-08's own controller/views instead of adding fields to this form.

## Client-Side YouTube Preview Is Best-Effort Only

The inline `<script>` in `modules/lessons/_form.blade.php` mirrors
`YoutubeSanitizerService`'s regex client-side purely to render the iframe
preview — it is **not** a security boundary. `StoreLessonRequest`/
`UpdateLessonRequest`'s `withValidator()` always re-validates through the
real `YoutubeSanitizerService` server-side; if you ever see a lesson with a
non-canonical `youtube_url` stored, the bug is in the controller/request,
not the preview script. Never trust the client-side match result for
anything beyond the visual preview.

## `FileUploadService` Path Mismatches

If a Lesson's `image_path`/`pdf_path` doesn't start with
`orgs/{course.org_id}/courses/{course.id}/...`, check that
`LessonController::handleMediaFields()` is passing `$module->course` (the
Lesson's actual parent Course), not `auth()->user()`'s own org — see
`courses-conventions` for why the service resolves the tenant from the
Course model, not the session.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change
to `CourseController`/`ModuleController`/`LessonController`, the
`courses*`/`modules*`/`lessons*` routes, the Blade views under
`resources/views/courses/`+`resources/views/modules/lessons/`, or
`ModuleReorder.js` **must** update all three courses skills
(`courses-architecture`, `courses-conventions`, `courses-maintenance`) in
the same change, before the task is considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if the change affects what a reviewer
  must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fails the build
  if any of the three `courses-*` skills is missing.

## Related Specs

- `spec/specs/05-courses-modules-and-content-management.md` — RF06, RF07.
- `auth-orgs-maintenance` — the analogous module this one mirrors
  (CSV import chunking vs. this module's AJAX reorder).
- `tenancy-maintenance` — the underlying `OrgScope` contract this module
  builds on.
