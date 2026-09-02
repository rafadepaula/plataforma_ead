---
name: courses-conventions
description: >
  Code patterns, snippets, guardrails for Courses/Modules/Lessons feature:
  FileUploadService/YoutubeSanitizerService usage,
  Course/Module/Lesson Policy conventions, factory conventions, AJAX reorder
  endpoint shape. Use when write controller, Policy, Form Request, or Service
  managing `Course`/`Module`/`Lesson` records, handle Lesson media upload or
  YouTube URL, or wire reorder endpoints.
license: MIT
metadata:
  feature: courses
  role: conventions
---

# Courses Conventions

## `FileUploadService`: Always Resolve Tenant From `Course`, Not User

`storeImages()`/`storePdfs()` take an **array** of `UploadedFile` plus the
`Course` model (single-file `storeImage()`/`storePdf()` remain for legacy
paths), not bare `course_id` int and not logged-in user. Org folder derived
from `$course->org_id` first, only fall back to
`auth()->user()->org_id ?? session('active_org_id')` if Course instance somehow
have no `org_id` yet. Matter because Admin impersonating Org B could otherwise
upload file into Org A's Course while writing to path derived from own session,
silently mismatching stored path against Course's real tenant:

```php
/**
 * @return list<string> stored paths, one per uploaded file
 */
public function storeImages(array $files, Course $course): array
{
    $path = "orgs/{$this->resolveOrgId($course)}/courses/{$course->id}/images";

    return array_values(array_map(
        fn (UploadedFile $file) => $file->store($path, 'public'),
        $files,
    ));
}
```

### Multi-file lesson form contract

Request keys are plural arrays; limits are PER FILE, validated per element:

```php
'images' => ['nullable', 'array'],
'images.*' => ['image', 'max:2048'],            // 2MB each
'pdfs' => ['nullable', 'array'],
'pdfs.*' => ['file', 'mimes:pdf', 'max:10240'], // 10MB each
'removed_media' => ['nullable', 'array'],
'removed_media.*' => ['integer'],
```

Note `removed_media.*` is only `['integer']` — no `exists:lesson_media,id`
rule. An id for another lesson/tenant is NOT a validation error; it is
silently ignored at the controller level instead (see below).

`LessonController::validatedAttributes()` strips the media-only inputs
(`images`, `pdfs`, `removed_media`) out of `$request->validated()` before
mass assignment, then `LessonController::syncMedia()`:

- creates one `lesson_media` row per stored file (`kind`, `path`,
  `original_name`, `size_bytes`), ADDITIVELY — new uploads never delete
  previously persisted attachments;
- keeps `lessons.image_path`/`pdf_path` synced with the first attachment of
  each kind (null when that kind's last attachment is removed) for legacy
  read paths;
- deletes only `removed_media[]` ids resolved through the route-bound
  lesson's own `media()` relation (ids from another lesson/org simply don't
  match the scoped query, so they are ignored rather than rejected), and
  `Storage::disk('public')->delete()`s each removed file;
- re-sanitizes `youtube_url` exactly as before — multi-file changed nothing
  in the YouTube path.

Tests fake disk (`Storage::fake('public')`) rather than touch real storage, and
assert on returned path's `dirname()` to confirm tenant isolation:

```php
Storage::fake('public');
$path = (new FileUploadService)->storeImage($file, $course);
$this->assertSame("orgs/{$course->org_id}/courses/{$course->id}/images", dirname($path));
```

## `YoutubeSanitizerService`: Whitelist, Don't Blacklist

`sanitize(string $url): string` match only `youtube.com/watch?v=`,
`youtube.com/embed/`, and `youtu.be/` (with optional `www.` and 11-character
video ID). Everything else, including `youtube-nocookie.com`, `javascript:`
URIs, and arbitrary `<iframe>` src values, fail *same* regex and throw
`InvalidYoutubeUrlException`. Never add second "reject known-bad patterns"
branch. Whitelist-only match is whole mitigation:

```php
private const PATTERN = '#^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$#i';

public function sanitize(string $url): string
{
    if (! preg_match(self::PATTERN, trim($url), $matches)) {
        throw new InvalidYoutubeUrlException("URL do YouTube inválida ou não suportada: \"{$url}\".");
    }

    return 'https://www.youtube.com/embed/'.$matches[1];
}
```

`InvalidYoutubeUrlException extends \InvalidArgumentException` specifically so it
never need `bootstrap/app.php` handler entry. Always caught locally, inside
`StoreLessonRequest`/`UpdateLessonRequest` `withValidator()`, and re-surfaced as
normal validation error on `youtube_url` field:

```php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator): void {
        $url = $this->input('youtube_url');
        if (! $url) {
            return;
        }

        try {
            app(YoutubeSanitizerService::class)->sanitize($url);
        } catch (InvalidYoutubeUrlException $e) {
            $validator->errors()->add('youtube_url', $e->getMessage());
        }
    });
}
```

Controller then re-sanitize already-validated value before persist
(`$data['youtube_url'] = $this->youtubeSanitizerService->sanitize($data['youtube_url'])`)
so row always store canonical embed URL, never raw input.

## Policies: Course Scope-Protected, Module/Lesson Not

`CoursePolicy` only need role check (`admin|gestor`) plus delete-time enrollment
guard. `OrgScope` already keep every `Course` query/route-model-binding confined
to acting Gestor's `org_id`:

```php
public function delete(User $user, Course $course): bool
{
    if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
        return false;
    }

    return ! $course->hasActiveEnrollments();
}
```

`ModulePolicy`/`LessonPolicy` have no scope to rely on, so they compare `org_id`
explicitly. Critically, they must load parent `Course` **bypassing `OrgScope`**
to do that comparison. Read through normal (scoped) relation while acting user
*different*-org Gestor return `null` (scope filter row out), turn intended 403
into `TypeError`/null-argument crash instead:

```php
protected function parentCourse(Module $module): Course
{
    return $module->course()->withoutGlobalScopes()->firstOrFail();
}

protected function authorizeForCourse(User $user, Course $course): bool
{
    if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
        return false;
    }

    if ($user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id !== (int) $course->org_id) {
        return false;
    }

    return true;
}
```

`LessonPolicy` do same one level deeper (`$lesson->module` — `Module` itself
never scoped, so that relation safe to read normally — then
`parentCourse($module)` for unscoped Course lookup).

`create` ability on `Module`/`Lesson` have no model instance yet, so it take
parent explicitly via Gate multi-argument form:
`Gate::authorize('create', [Lesson::class, $module])` calls
`LessonPolicy::create(User $user, Module $module)`.

## Factories: Explicit Parent, Content-Kind States

`ModuleFactory`/`LessonFactory` mirror `CourseFactory` convention of leaving
parent FK out of `definition()`. Set it via `->for()`:

```php
Module::factory()->for(Course::factory())->create();
Lesson::factory()->for($module)->richText()->create();
```

`LessonFactory` base `definition()` leave all four content columns
(`content_text`/`image_path`/`pdf_path`/`youtube_url`) `null`. Each of the
four content kinds opt-in via dedicated state
(`richText()`/`withImage()`/`withPdf()`/`withYoutube()`) that populate only own
column(s) and clear other three, so test creating "YouTube lesson" never
accidentally also carry stray `content_text`.

`LessonMediaFactory` follows the same convention (`lesson_id` out of
`definition()`, set via `->for($lesson, 'lesson')` or `['lesson_id' => ...]`);
default definition is an image attachment, `pdf()` state flips the kind.

## Reorder Endpoints: Dense Server-Side Reassignment, Scoped Validation

`order_index` have no DB-level uniqueness constraint, so reorder endpoints
(`ModuleController::reorder()` / `LessonController::reorder()`) must not trust
client-supplied index values directly. They re-derive dense `0..n-1` sequence
from submitted `ordered_ids` array order:

```php
foreach (array_values($orderedIds) as $index => $id) {
    $lessons->get($id)->update(['order_index' => $index]);
}
```

Both endpoints also verify every submitted ID actually belong to route-bound
parent (`$course`/`$module`) **before** writing anything.
`ReorderModulesRequest`/`ReorderLessonsRequest` validate shape only
(`ordered_ids.*` exists in `modules`/`lessons`); controller is what confirm *set*
of IDs match parent's own children 1:1:

```php
$lessons = $module->lessons()->whereIn('id', $orderedIds)->get()->keyBy('id');

if ($lessons->count() !== count($orderedIds)) {
    return response()->json(['message' => 'Uma ou mais lições não pertencem a este módulo.'], 422);
}
```

Without this check, Gestor could reorder — or leak existence of — other org's
rows by guessing IDs, since reorder route only route-model-bind parent, not each
child ID individually.

## Course Catalog Composition

`resources/views/courses/index.blade.php` composes shared widgets plus two domain
components:

- `<x-course.title-cell>`: title; zero-module caption; module/lesson pluralization.
- `<x-course.row-actions>`: Módulos tonal, Regras de conclusão ghost, Editar
  ghost, Remover critical icon.

Catalog uses one `<x-ui.data-table>` markup. Mobile card presentation comes from
canonical table reflow; never add parallel desktop/mobile loops or duplicate
`dusk` selectors. Explicit header slot applies
`course-catalog-*-column`; `_courses.scss` owns 150/110/130/470px widths and
right alignment.

Controller passes `search`, normalized `status`, paginator aggregates. Filter
form submits GET with scalar `search` and `status=all|published|draft`. Preserve
query string across pages.

Active enrollment action rule:

- `active_students_count > 0`: disabled delete button, no modal target, caption
  with count.
- zero active: modal trigger rendered; modal exists outside table wrapper;
  `form-dusk="delete-form-{id}"` targets actual DELETE `<form>`.

Four actions stay visible here despite generic three-action guideline: catalog
contract reserves 470px action column and requires all four.

## Trail-Builder UI Composition

Module/lesson lists and the lesson form compose four wrapper components
(created for the trail builder, NOT part of the core 30-component
`<x-ui.*>` inventory):

- `<x-ui.sortable-list>`: `<ul data-reorder-url="..." class="ds-sortable-list">`
  with `dusk="module-list|lesson-list"` passthrough.
- `<x-ui.sortable-row>`: `<li data-id draggable="true" dusk="{type}-row-{id}">`
  with the 4 zones — `grip-vertical` `drag-handle`, single-line ellipsis title
  (secondary color when unpublished), chips slot (`"{N} lições"` /
  `Conteúdo`|`Quiz` + neutral `Não publicada`), actions slot. `"{N} lições"`
  comes from `ModuleController::index()`'s `withCount('lessons')`.
- `<x-ui.file-drop>`: dashed dropzone for `name="images[]"`/`pdfs[]`; `dusk`
  prop carries `lesson-image-input`/`lesson-pdf-input`; renders the persisted
  attachment list with `dusk="remove-file-{{ $id }}"` remove buttons.
- `<x-ui.youtube-field>`: `dusk="lesson-youtube-input"` input plus 16:9
  preview — pastel-wash empty state, `dusk="youtube-preview"` iframe when a
  valid URL is typed.

Destructive deletes go through `<x-ui.confirm-modal>` with the `confirmDusk`
prop so the modal's confirm submit carries the frozen selector
(`delete-module-{id}`/`delete-lesson-{id}`); the row renders only the ghost
trash trigger, and `formDusk` keeps `delete-module-form-{id}` on the embedded
form. Module deletion must quote the real lesson count in the cascade message
("As {N} lições deste módulo também serão removidas. Esta ação não poderá ser
desfeita."), including the zero-lesson wording branch. Lesson-form
interactivity lives in the `LessonForm.js` module (type-select toggling, live
sanitized preview, client-side size checks) — never in an inline `<script>`.
