---
name: courses-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Courses/Modules/
  Lessons feature (SPEC-05): FileUploadService/YoutubeSanitizerService usage,
  Course/Module/Lesson Policy conventions, factory conventions, and the AJAX
  reorder endpoint shape. Use whenever writing a controller, Policy, Form
  Request, or Service that manages `Course`/`Module`/`Lesson` records, handles
  a Lesson media upload or YouTube URL, or wires the reorder endpoints.
license: MIT
metadata:
  feature: courses
  role: conventions
  specs:
    - spec/specs/05-courses-modules-and-content-management.md
---

# Courses Conventions

## `FileUploadService`: Always Resolve the Tenant From the `Course`, Not the User

`storeImage()`/`storePdf()` take the `UploadedFile` **and the `Course`
model**, not a bare `course_id` int and not the logged-in user — the org
folder is derived from `$course->org_id` first, only falling back to
`auth()->user()->org_id ?? session('active_org_id')` if the Course instance
somehow has no `org_id` yet. This matters because an Admin impersonating Org
B could otherwise be routed to upload a file into Org A's Course while
writing to a path derived from their own session, silently mismatching the
stored path against the Course's real tenant:

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

Callers (`LessonController::handleMediaFields()`) always pass the Lesson's
parent Course (`$module->course`), delete the previous file on replacement,
and `unset()` the raw `UploadedFile` key before mass-assigning the rest of
the validated data — same pattern as `auth-orgs-conventions`' logo upload
section:

```php
if ($request->hasFile('image')) {
    if ($lesson?->image_path) {
        Storage::disk('public')->delete($lesson->image_path);
    }
    $data['image_path'] = $this->fileUploadService->storeImage($request->file('image'), $course);
}
unset($data['image']);
```

Tests fake the disk (`Storage::fake('public')`) rather than touching real
storage, and assert on the returned path's `dirname()` to confirm tenant
isolation:

```php
Storage::fake('public');
$path = (new FileUploadService)->storeImage($file, $course);
$this->assertSame("orgs/{$course->org_id}/courses/{$course->id}/images", dirname($path));
```

## `YoutubeSanitizerService`: Whitelist, Don't Blacklist

`sanitize(string $url): string` matches only `youtube.com/watch?v=`,
`youtube.com/embed/`, and `youtu.be/` (with an optional `www.` and an 11
-character video ID) — everything else, including `youtube-nocookie.com`,
`javascript:` URIs, and arbitrary `<iframe>` src values, fails the *same*
regex and throws `InvalidYoutubeUrlException`. Never add a second
"reject known-bad patterns" branch — a whitelist-only match is the whole
mitigation:

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

`InvalidYoutubeUrlException extends \InvalidArgumentException` specifically
so it never needs a `bootstrap/app.php` handler entry — it is always caught
locally, inside `StoreLessonRequest`/`UpdateLessonRequest`'s
`withValidator()`, and re-surfaced as a normal validation error on the
`youtube_url` field:

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

The controller then re-sanitizes the already-validated value before persisting
(`$data['youtube_url'] = $this->youtubeSanitizerService->sanitize($data['youtube_url'])`)
so the row always stores the canonical embed URL, never the raw input.

## Policies: Course Is Scope-Protected, Module/Lesson Are Not

`CoursePolicy` only needs a role check (`admin|gestor`) plus the delete-time
enrollment guard — `OrgScope` already keeps every `Course` query/route-model
-binding confined to the acting Gestor's `org_id`:

```php
public function delete(User $user, Course $course): bool
{
    if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
        return false;
    }

    return ! $course->hasActiveEnrollments();
}
```

`ModulePolicy`/`LessonPolicy` have no scope to rely on, so they explicitly
compare `org_id` — and critically, they must load the parent `Course`
**bypassing `OrgScope`** to do that comparison. Reading it through the
normal (scoped) relation while the acting user is a *different*-org Gestor
returns `null` (the scope filters the row out), turning an intended 403 into
a `TypeError`/null-argument crash instead:

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

`LessonPolicy` does the same one level deeper (`$lesson->module` — `Module`
itself is never scoped, so that relation is safe to read normally — then
`parentCourse($module)` for the unscoped Course lookup).

The `create` ability on `Module`/`Lesson` has no model instance yet, so it
takes the parent explicitly via Gate's multi-argument form:
`Gate::authorize('create', [Lesson::class, $module])` →
`LessonPolicy::create(User $user, Module $module)`.

## Factories: Explicit Parent, Content-Kind States

`ModuleFactory`/`LessonFactory` mirror `CourseFactory`'s convention of
leaving the parent FK out of `definition()` — set it via `->for()`:

```php
Module::factory()->for(Course::factory())->create();
Lesson::factory()->for($module)->richText()->create();
```

`LessonFactory`'s base `definition()` leaves all four content columns
(`content_text`/`image_path`/`pdf_path`/`youtube_url`) `null` — each of
RF07's four content kinds is opt-in via a dedicated state
(`richText()`/`withImage()`/`withPdf()`/`withYoutube()`) that populates only
its own column(s) and clears the other three, so a test creating a
"YouTube lesson" never accidentally also carries a stray `content_text`.

## Reorder Endpoints: Dense Server-Side Reassignment, Scoped Validation

`order_index` has no DB-level uniqueness constraint, so the reorder
endpoints (`ModuleController::reorder()` / `LessonController::reorder()`)
must not trust client-supplied index values directly — they re-derive a
dense `0..n-1` sequence from the submitted `ordered_ids` array order:

```php
foreach (array_values($orderedIds) as $index => $id) {
    $lessons->get($id)->update(['order_index' => $index]);
}
```

Both endpoints also verify every submitted ID actually belongs to the
route-bound parent (`$course`/`$module`) **before** writing anything —
`ReorderModulesRequest`/`ReorderLessonsRequest` validate shape only
(`ordered_ids.*` exists in `modules`/`lessons`), the controller is what
confirms the *set* of IDs matches the parent's own children 1:1:

```php
$lessons = $module->lessons()->whereIn('id', $orderedIds)->get()->keyBy('id');

if ($lessons->count() !== count($orderedIds)) {
    return response()->json(['message' => 'Uma ou mais lições não pertencem a este módulo.'], 422);
}
```

Without this check, a Gestor could reorder — or leak the existence of —
another org's rows by guessing IDs, since the reorder route only route
-model-binds the parent, not each child ID individually.
