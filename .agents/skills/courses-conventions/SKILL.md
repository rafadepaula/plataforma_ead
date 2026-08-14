---
name: courses-conventions
description: >
  Code patterns, snippets, guardrails for Courses/Modules/Lessons feature
  (SPEC-05): FileUploadService/YoutubeSanitizerService usage,
  Course/Module/Lesson Policy conventions, factory conventions, AJAX reorder
  endpoint shape. Use when write controller, Policy, Form Request, or Service
  managing `Course`/`Module`/`Lesson` records, handle Lesson media upload or
  YouTube URL, or wire reorder endpoints.
license: MIT
metadata:
  feature: courses
  role: conventions
  specs:
    - spec/specs/05-courses-modules-and-content-management.md
---

# Courses Conventions

## `FileUploadService`: Always Resolve Tenant From `Course`, Not User

`storeImage()`/`storePdf()` take `UploadedFile` **and `Course` model**, not bare
`course_id` int and not logged-in user. Org folder derived from
`$course->org_id` first, only fall back to
`auth()->user()->org_id ?? session('active_org_id')` if Course instance somehow
have no `org_id` yet. Matter because Admin impersonating Org B could otherwise
upload file into Org A's Course while writing to path derived from own session,
silently mismatching stored path against Course's real tenant:

```php
public function storeImage(UploadedFile $file, Course $course): string
{
    return $this->store($file, $course, 'images');
}

protected function store(UploadedFile $file, Course $course, string $kind): string
{
    $orgId = $this->resolveOrgId($course); // $course->org_id first
    $path = "orgs/{$orgId}/courses/{$course->id}/{$kind}";

    return $file->store($path, 'public'); // 'public' disk
}
```

Callers (`LessonController::handleMediaFields()`) always pass Lesson's parent
Course (`$module->course`), delete previous file on replacement, and `unset()`
raw `UploadedFile` key before mass-assign rest of validated data. Same pattern as
`auth-orgs-conventions` logo upload section:

```php
if ($request->hasFile('image')) {
    if ($lesson?->image_path) {
        Storage::disk('public')->delete($lesson->image_path);
    }
    $data['image_path'] = $this->fileUploadService->storeImage($request->file('image'), $course);
}
unset($data['image']);
```

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
(`content_text`/`image_path`/`pdf_path`/`youtube_url`) `null`. Each of RF07's
four content kinds opt-in via dedicated state
(`richText()`/`withImage()`/`withPdf()`/`withYoutube()`) that populate only own
column(s) and clear other three, so test creating "YouTube lesson" never
accidentally also carry stray `content_text`.

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
