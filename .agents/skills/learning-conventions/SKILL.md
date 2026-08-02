---
name: learning-conventions
description: >
  Concrete code patterns for the Student Learning & Progress feature
  (SPEC-07): `MarkLessonCompleteAction` usage, the `LessonProgressController`
  422-shape-guard pattern, `withoutGlobalScopes()` Course resolution, and
  the `LessonPlayer.js` video-polling/manual-completion conventions. Use
  whenever writing a controller, action, or JS module that touches
  `lesson_progress`, gates a student-facing route, or renders/updates
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

Never `LessonProgress::updateOrCreate()`/manual save from a controller —
route every completion through `app/Actions/MarkLessonCompleteAction.php`
so the idempotency, `GREATEST(watched_seconds)`, and transition-gated
`LessonMarkedAsCompleted` dispatch stay in one place:

```php
$progress = $this->markLessonCompleteAction->execute(
    $lesson,
    $request->user(),
    'video_threshold', // or 'manual_click' / (future) 'quiz_passed'
    $watchedSeconds,   // null for non-video completions
);
```

The one exception is the video endpoint's **below-threshold** path
(`LessonProgressController::updateProgress()`): persisting an
in-progress `watched_seconds` value that has not yet crossed 90% is not a
completion, so it writes `LessonProgress` directly (`firstOrNew` +
`GREATEST` + `save()`) rather than calling the Action — do not route that
branch through `MarkLessonCompleteAction`, it would incorrectly dispatch
`LessonMarkedAsCompleted` for an unfinished view.

## The 422 Shape-Guard Pattern on Both Progress Endpoints

Both `LessonProgressController` actions validate the lesson **shape**
before touching `lesson_progress`, checking `type === 'quiz'` first
(quiz completion is reserved for SPEC-08's `SubmitQuizAttemptAction`) so
malformed data carrying both `type=quiz` and a `youtube_url` still 422s
as a quiz:

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

Both actions also 404 an unpublished lesson for an Aluno specifically
(`! $lesson->is_published && $user->hasRole(RolesEnum::ALUNO->value)`),
mirroring `ClassroomController`'s same check — Admin/Gestor retain
preview access to draft lessons, so the role check is not optional.

## Resolving `Course` Inside This Module: Always `withoutGlobalScopes()`

Every Course lookup in this module — `ClassroomController::show()`,
`EnsureStudentIsEnrolled::resolveCourse()`,
`RecalculateCourseProgress::handle()`, `StudentCourseController::index()` —
bypasses `OrgScope` explicitly, because an Aluno carries no `org_id` of
their own and the scope would otherwise filter every result out from
under them:

```php
// Route param already a Course instance? Use it. Otherwise resolve raw:
$course = Course::query()->withoutGlobalScopes()->findOrFail($courseId);

// From a Lesson, cascade up the same way:
$course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
```

Never accept a typed `Course $course` implicit route-model binding on a
student-facing route in this module — implicit binding uses the model's
default (scoped) query and will silently 404 an Aluno viewing a course
outside their own (nonexistent) `org_id`. `ClassroomController::show()`
takes `int $course` for exactly this reason.

When a controller needs the Course cached onto a relation the view reads
directly (e.g. `$lesson->module->course`), set it explicitly so a later
access doesn't re-trigger the scoped, empty-for-an-Aluno query:

```php
$course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
$lesson->module->setRelation('course', $course);
```

## `LessonPlayer.js`: Two Independent Bindings, One Public Test Seam

`resources/js/modules/LessonPlayer.js` binds two unrelated things on
`init()` — `bindManualCompletion()` for `[data-mark-complete-url]`
buttons, `bindVideoPlayers()` for `[data-youtube-player]` containers —
and both funnel UI feedback through the same `reflectCompletion()`/
`notify()` helpers so a manual click and a video auto-completion look
identical to the student.

`reportProgress(lessonId, watchedSeconds, durationSeconds)` is
**intentionally public**: it is the real 5s-poll callback, but it is also
the exact seam `tests/Browser/VideoThresholdCompletionTest.php` calls
directly (`window.LessonPlayer.reportProgress(...)`) to simulate crossing
the 90% threshold without depending on YouTube's real IFrame API inside
headless Dusk. Do not rename or make this private — a Dusk regression
here means the test file, not the JS, needs updating in lockstep.

## Badge/Button Visibility: Don't Rely on Bare `hidden` Toggling Against `<x-ui.badge>`

`<x-ui.badge>` bakes `display: inline-flex` into its own inline `style`,
so toggling the native `hidden` attribute cannot win against it. Reveal a
completion badge with an explicit inline-style override instead, appended
after the component's own `style` via `ComponentAttributeBag::merge()`:

```blade
<x-ui.badge data-completion-badge style="display:none;">Concluída</x-ui.badge>
```

```js
badge.style.display = 'inline-flex'; // not badge.classList.remove('hidden')
```

Also, a bare `@if(...) hidden @endif` (or a bare `{{ $expr }}`) inside an
`<x-component ...>` tag's own attribute list breaks Blade's component-tag
attribute compiler — use the dynamic-attribute binding form instead:

```blade
{{-- wrong: desyncs component-tag compilation --}}
<x-ui.button @if($isCompleted) hidden @endif>...</x-ui.button>

{{-- correct --}}
<x-ui.button :hidden="$isCompleted">...</x-ui.button>
```
