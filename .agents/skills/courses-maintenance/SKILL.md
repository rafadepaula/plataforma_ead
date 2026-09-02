---
name: courses-maintenance
description: >
  Debug, test, edge-case guide for Courses/Modules/Lessons feature:
  Blade views, `ModuleReorder.js` AJAX reorder module, mandatory PHPUnit/Dusk
  test files. Use when `MultiTenantCourseManagementTest`, `ModuleReorderTest`, or
  `LessonMultimediaTest` fail; drag-and-drop reorder not persisting; Lesson file
  upload land in wrong tenant folder; or YouTube embed preview show for URL that
  should be rejected.
license: MIT
metadata:
  feature: courses
  role: maintenance
---

# Courses Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/MultiTenantCourseManagementTest.php` — Gestor CRUD scoped to own
  org (`OrgScope` on `Course`); cross-tenant isolation (Gestor guessing other
  org's Course/Module ID get 404/403, never data); delete guard return 422 when
  Course have `active` enrollment (cancelled/completed enrollments do **not**
  block deletion); `role:aluno` forbidden from every
  `courses*`/`modules*`/`lessons*` route.
- `tests/Feature/ModuleReorderTest.php` — AJAX reorder endpoint persist dense
  `0..n-1` `order_index` sequence and reject any submitted id not belonging to
  route-bound parent (another course, or another org's course/module entirely).
- `tests/Feature/LessonMultimediaTest.php` — the four content kinds (Rich
  Text/Imagem/PDF/YouTube), multi-file `images[]`/`pdfs[]` contract (2 images +
  2 PDFs -> 4 `lesson_media` rows under `orgs/{org_id}/courses/{course_id}/`),
  per-file limits (image >2MB / pdf >10MB rejected atomically, no partial
  upload), `removed_media[]` row+file deletion (foreign ids ignored),
  `YoutubeSanitizerService` rejecting non-YouTube/XSS-style URLs, lesson
  management screen GETs, malformed YouTube URL on the UPDATE path, and
  `FileUploadService` throwing `UnresolvedOrgContextException` for an
  unresolvable org.
- `tests/Feature/LessonMediaTest.php` — `lesson_media` model layer: legacy
  `image_path`/`pdf_path` backfill migration (plus idempotency re-run), the
  `Lesson::media()`/`images()`/`pdfs()` relations, `LessonMediaFactory` sync,
  `lessons_count` excluding soft-deleted lessons (feeds the "{N} lições"
  chip), and classroom multi-image/multi-pdf rendering with legacy fallback.
- `tests/Feature/UiSortableFieldComponentsTest.php` — the four wrapper
  components in isolation: `<x-ui.sortable-list>`/`<x-ui.sortable-row>`
  markup and dusk passthrough, `<x-ui.file-drop>` attachment list/remove
  button/`.is-invalid` convention, `<x-ui.youtube-field>` filled-vs-empty
  server-rendered preview.
- `tests/Unit/Services/YoutubeSanitizerServiceTest.php`,
  `tests/Unit/Services/FileUploadServiceTest.php` — service-level unit coverage,
  independent of HTTP layer.
- `tests/Browser/CourseManagementTest.php` — E2E: Gestor creates/edits/deletes
  Course, Module, and Lesson through UI.
- `tests/Browser/ModuleReorderTest.php` — E2E: reorder persist across full page
  reload.
- `tests/Browser/ModuleAndLessonManagementDuskTest.php` — E2E of the
  full trail-builder selector contract: module rows + "{N} lições" chip
  (zero-lesson wording included), cascade ConfirmModal deletion quoting the
  real lesson count, lesson rows with `Conteúdo`/`Não publicada` chips,
  multi-file attach on `lesson-image-input`/`lesson-pdf-input`, YouTube preview
  asserted via `src` attribute (never iframe load — network race), type-select
  quiz hiding, and reorder round-trip via `persistOrder()`. Also carries the
  exception-flow negatives the happy-path lifecycle never touches:
  `test_lesson_form_validation_rejections` (invalid YouTube URL rejected with
  `error-youtube_url`, an oversized image rejected CLIENT-SIDE by
  `LessonForm.js`'s own `.is-invalid` toggle with no server round-trip, and an
  oversized/wrong-type PDF that bypasses that client gate — via the
  `attachFileBypassingClientValidation()` helper, which sets `input.files`
  without dispatching `change` — to hit the real server 422 on `error-pdfs`)
  and `test_module_and_lesson_management_forbidden_across_tenants` (module
  edit, lesson list, and lesson edit all render a real 403 page for a
  cross-org Gestor guessing another org's id).

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=MultiTenantCourseManagementTest
vendor/bin/sail artisan test --filter=ModuleReorderTest
vendor/bin/sail artisan test --filter=LessonMultimediaTest
vendor/bin/sail artisan test --filter=LessonMediaTest
vendor/bin/sail dusk --filter=ModuleReorderTest
vendor/bin/sail dusk --filter=ModuleAndLessonManagementDuskTest
```

## `ModuleReorder.js` — Why Native Drag-and-Drop, Not jQuery

The original requirement text literally said "Reordenação de módulos via
AJAX/jQuery", but
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

    if (!url || orderedIds.length === 0) return;

    const snapshot = Array.from(list.children); // captured BEFORE the POST

    try {
        await this.httpClient.post(url, { ordered_ids: orderedIds });
        this.notify('success', 'Ordem atualizada com sucesso.');
    } catch (error) {
        list.replaceChildren(...snapshot); // revert: UI must not lie about persisted order
        this.notify('error', `Falha ao reordenar: ${error.message}`);
    }
}
```

Failure reversion: the child order is snapshotted before the POST and the DOM
is restored on any non-2xx, otherwise the list shows an order the server never
accepted. Two Gestors reordering concurrently is last-write-wins (acceptable,
documented) — the dense `0..n-1` reassignment makes each write self-healing.

Keyboard-accessible move-up/move-down buttons (WCAG AA) swap one adjacent pair in the DOM, then call the SAME
`persistOrder(list)` — identical endpoint, identical payload shape, so the
controller's cross-tenant `whereIn + count` 422 guard cannot be bypassed by
the button path. Do not invent a second endpoint or payload for them.

`window.ModuleReorder` stays exposed globally for the Dusk suite (see below).

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

`modules/lessons/_form.blade.php` `type` select offer `quiz` as option (the
`lessons.type` column is `content|quiz`), but this feature's form only ever
expose four `content`-kind fields (Rich Text/Imagem/PDF/YouTube). The quizzes
module own quiz question authoring. If asked to "wire up quiz creation", that
out of scope here; extend the quizzes module's own controller/views instead of
adding fields to this form.

## Client-Side YouTube Preview Best-Effort Only

The preview lives in the `LessonForm.js` module (`x-youtube-field` renders the
iframe) and mirror `YoutubeSanitizerService` regex client-side purely to render
the 16:9 preview. It **not** security boundary.
`StoreLessonRequest`/`UpdateLessonRequest` `withValidator()` always re-validate
through real `YoutubeSanitizerService` server-side; if you ever see lesson with
non-canonical `youtube_url` stored, bug in controller/request, not preview JS.
Never trust client-side match result for anything beyond visual preview.

Dusk gotcha: waiting on the external YouTube iframe can hang on
network — assert the `src` ATTRIBUTE (`assertAttributeContains('@youtube-preview',
'src', '.../embed/...')`), never wait for the iframe to load, and never race a
submit against a YouTube-bearing edit form.

## `FileUploadService` Path Mismatches

If a `lesson_media.path` (or legacy `lessons.image_path`/`pdf_path`) not start
with `orgs/{course.org_id}/courses/{course.id}/...`, check
`LessonController::syncMedia()` pass `$module->course` (Lesson's actual
parent Course), not `auth()->user()`'s own org. See `courses-conventions` for why
service resolve tenant from Course model, not session.

## Multi-file Upload Gotchas

- A 10MB PDF may be rejected by PHP (`upload_max_filesize`/`post_max_size`)
  BEFORE Laravel validation runs — then the error lands on the whole request,
  not `pdfs.0`. Confirm the Sail `php.ini` limits when a Feature test expects a
  per-field validation error but gets a generic post-too-large.
- Validation is per-file and ATOMIC: a request mixing one 3MB image with valid
  files must create NO lesson row, NO `lesson_media` row, and store NOTHING —
  if you see partial uploads, `syncMedia()` is running before validation fails
  or catching the exception too broadly.
- Client-side removal only clears the UI; the server deletion happens on the
  next update via `removed_media[]` ids. Ids belonging to another lesson/org
  are silently ignored (ownership check inside `syncMedia()`, scoped through
  the route-bound lesson's own `media()` relation), so a crafted
  `removed_media[]` can never delete a neighbor tenant's file.
- Dusk multi-file attach: `Browser::attach()` takes EXACTLY ONE path (its
  `LocalFileDetector` upload cannot carry an array, and chromedriver's session
  cannot read host paths newline-joined into one send-keys payload — it fails
  with `File not found`). To set several files on a `multiple` input, use the
  `attachManyFiles()` helper in `tests/Browser/ModuleAndLessonManagementDuskTest.php`:
  it builds real `File` objects in-page from base64 bytes, assigns
  `input.files = dataTransfer.files` and dispatches a bubbling `change` — the
  same `DataTransfer` route `LessonForm.js` itself uses on a drop.

## ConfirmModal `confirmDusk` Selector Migration

Before the trail-builder refactor, `delete-module-{id}`/`delete-lesson-{id}` sat on raw inline
submit buttons inside the row. After the ConfirmModal refactor, those dusk
selectors moved to the modal's confirm submit (via `<x-ui.confirm-modal
confirmDusk="...">`); the row only renders the trash trigger. If a Dusk test
fails to find `@delete-module-{id}` on the listing, it is asserting against
the pre-modal layout — open the modal first (click the row's
`[data-bs-target*="delete-module-modal-{id}"]` trigger — the modal id is
`delete-module-modal-{id}`/`delete-lesson-modal-{id}`, and a `*=` contains
check for the bare `delete-module-{id}` does NOT match it),
`waitForModalShown()`, then click `@delete-module-{id}`. The cascade warning
must quote the REAL lesson
count ("As {N} lições deste módulo também serão removidas. Esta ação não poderá
ser desfeita."), including the N=0 wording branch.

## Auto-Update Protocol

Any change to
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

## Related

- `auth-orgs-maintenance` — analogous module this one mirror (CSV import chunking
  vs this module's AJAX reorder).
- `tenancy-maintenance` — underlying `OrgScope` contract this module build on.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle chain)** —
one method drive create, edit, state change, delete, consequence — **not** by
module or feature. Consequences when maintaining this module:

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

## Gestor Catalog Verification

Focused backend/component gates:

```bash
vendor/bin/sail artisan test --compact tests/Feature/MultiTenantCourseManagementTest.php
vendor/bin/sail artisan test --compact tests/Feature/UiTableComponentTest.php
vendor/bin/sail artisan test --compact tests/Feature/Theme/DuskSelectorContractTest.php
```

Browser gate requires fresh assets:

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan dusk --filter=CourseManagementTest
vendor/bin/sail artisan dusk --filter=test_courses_index_management_screen_has_no_horizontal_scroll
```

Catalog checks: partial/combined filters, array-shaped query input, 10-row
pagination with retained query string, total versus active enrollment counts,
five columns, filtered empty state, four visible actions, unique selectors,
modal content/form, soft delete, protected course without modal.

Common failures:

- Active course still opens modal: query missing `active_students_count`, or
  row action reads `students_count`.
- Duplicate/English pagination summary: shared component fell back to framework
  `bootstrap-5` view instead of internal links-only views.
- Mobile horizontal overflow: numbered pagination branch visible below `sm`, or
  catalog column widths not reset below `md`.
- Snapshot failure after component extraction: keep selector literals at
  `courses/index.blade.php` call site through `*-dusk` props.

Harness audit command:

```bash
vendor/bin/sail php scripts/check-skills.php
```
