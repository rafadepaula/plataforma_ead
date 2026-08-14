---
name: learning-conventions
description: >
  Concrete code patterns for Student Learning & Progress feature
  (SPEC-07): `MarkLessonCompleteAction` usage, `LessonProgressController`
  422-shape-guard pattern, `withoutGlobalScopes()` Course resolution,
  `LessonPlayer.js` video-polling/manual-completion conventions. Use
  whenever write controller, action, or JS module touching
  `lesson_progress`, gate student-facing route, or render/update
  lesson-completion UI.
license: MIT
metadata:
  feature: learning
  role: conventions
  specs:
    - spec/specs/07-student-learning-experience-and-progress.md
---

# Learning Conventions

## Always Write `lesson_progress` Through `MarkLessonCompleteAction`

Never `LessonProgress::updateOrCreate()`/manual save from controller.
Route every completion through `app/Actions/MarkLessonCompleteAction.php`
so idempotency, `GREATEST(watched_seconds)`, transition-gated
`LessonMarkedAsCompleted` dispatch stay in one place:

```php
$progress = $this->markLessonCompleteAction->execute(
    $lesson,
    $request->user(),
    'video_threshold', // or 'manual_click' / (future) 'quiz_passed'
    $watchedSeconds,   // null for non-video completions
);
```

One exception: video endpoint's **below-threshold** path
(`LessonProgressController::updateProgress()`). Persisting in-progress
`watched_seconds` not yet past 90% is not completion, so it writes
`LessonProgress` directly (`firstOrNew` + `GREATEST` + `save()`) instead of
calling Action. Do not route that branch through
`MarkLessonCompleteAction` — it would wrongly dispatch
`LessonMarkedAsCompleted` for unfinished view.

## The 422 Shape-Guard Pattern on Both Progress Endpoints

Both `LessonProgressController` actions validate lesson **shape** before
touching `lesson_progress`, checking `type === 'quiz'` first (quiz
completion reserved for SPEC-08's `SubmitQuizAttemptAction`), so malformed
data carrying both `type=quiz` and `youtube_url` still 422s as quiz:

```php
// complete(): rejects quiz lessons and video lessons
if ($lesson->type === 'quiz' || ! empty($lesson->youtube_url)) {
    return response()->json(['message' => '...'], 422);
}

// updateProgress(): rejects quiz lessons and non-video lessons
if ($lesson->type === 'quiz' || empty($lesson->youtube_url)) {
    return response()->json(['message' => '...'], 422);
}
```

Both actions also 404 unpublished lesson for Aluno specifically
(`! $lesson->is_published && $user->hasRole(RolesEnum::ALUNO->value)`),
mirroring `ClassroomController`'s same check. Admin/Gestor keep preview
access to draft lessons, so role check not optional.

## Resolving `Course` Inside This Module: Always `withoutGlobalScopes()`

Every Course lookup in this module — `ClassroomController::show()`,
`EnsureStudentIsEnrolled::resolveCourse()`,
`RecalculateCourseProgress::handle()`, `StudentCourseController::index()` —
bypasses `OrgScope` explicitly, because Aluno carries no own `org_id` and
scope would filter every result out from under them:

```php
// Route param already a Course instance? Use it. Otherwise resolve raw:
$course = Course::query()->withoutGlobalScopes()->findOrFail($courseId);

// From a Lesson, cascade up the same way:
$course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
```

Never accept typed `Course $course` implicit route-model binding on
student-facing route in this module. Implicit binding uses model's default
(scoped) query and will silently 404 Aluno viewing course outside their own
(nonexistent) `org_id`. `ClassroomController::show()` takes `int $course`
for exactly this reason.

When controller needs Course cached onto relation the view reads directly
(e.g. `$lesson->module->course`), set it explicitly so later access does
not re-trigger scoped, empty-for-Aluno query:

```php
$course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
$lesson->module->setRelation('course', $course);
```

## `LessonPlayer.js`: Two Independent Bindings, One Public Test Seam

`resources/js/modules/LessonPlayer.js` binds two unrelated things on
`init()` — `bindManualCompletion()` for `[data-mark-complete-url]` buttons,
`bindVideoPlayers()` for `[data-youtube-player]` containers. Both funnel UI
feedback through same `reflectCompletion()`/`notify()` helpers, so manual
click and video auto-completion look identical to student.

`reportProgress(lessonId, watchedSeconds, durationSeconds)` is
**intentionally public**: it is real 5s-poll callback, and also exact seam
`tests/Browser/VideoThresholdCompletionTest.php` calls directly
(`window.LessonPlayer.reportProgress(...)`) to simulate crossing 90%
threshold without depending on YouTube's real IFrame API inside headless
Dusk. Do not rename it, do not make it private. Dusk regression here means
test file needs updating in lockstep, not JS.

## Badge/Button Visibility: Don't Rely on Bare `hidden` Toggling Against `<x-ui.badge>`

`<x-ui.badge>` bakes `display: inline-flex` into own inline `style`, so
toggling native `hidden` attribute cannot win against it. Reveal completion
badge with explicit inline-style override instead, appended after
component's own `style` via `ComponentAttributeBag::merge()`:

```blade
<x-ui.badge data-completion-badge style="display:none;">Concluída</x-ui.badge>
```

```js
badge.style.display = 'inline-flex'; // not badge.classList.remove('hidden')
```

Also: bare `@if(...) hidden @endif` (or bare `{{ $expr }}`) inside
`<x-component ...>` tag's own attribute list breaks Blade's component-tag
attribute compiler. Use dynamic-attribute binding form instead:

```blade
{{-- wrong: desyncs component-tag compilation --}}
<x-ui.button @if($isCompleted) hidden @endif>...</x-ui.button>

{{-- correct --}}
<x-ui.button :hidden="$isCompleted">...</x-ui.button>
```
