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
    - spec/specs/26-student-course-catalog-meus-cursos.md
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

**One deliberate exception**: `StudentCourseController::index()` calls
`$user->courses()->withoutGlobalScope('org')` — bypassing only the `org`
scope, not the full `withoutGlobalScopes()` — because the catalog must
still exclude a soft-deleted Course (its `course_user` row is not
cascade-removed by a soft delete). Dropping every scope there would let a
soft-deleted Course's card render. Copy this narrower, named-scope form
only for a query that must also keep `SoftDeletes`' own scope; every other
lookup in this module keeps using the blanket `withoutGlobalScopes()` form
above.

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

## Badge/Button Visibility: Toggle Bootstrap's `.d-none`, Never `hidden`/`style.display`

Since the Bootstrap migration (Fase 1-5), `<x-ui.badge>`/`<x-ui.button>`
carry no inline `style` — visibility on both
`[data-mark-complete-url]`/`[data-completion-badge]` is expressed purely
with Bootstrap's `.d-none` utility class, set server-side via Blade's
`@class()` directive and toggled client-side with
`classList.add('d-none')`/`classList.remove('d-none')`:

```blade
<x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge"
    @class(['d-none' => ! ($isCompleted ?? false)])>
    Concluída
</x-ui.badge>
```

```js
// LessonPlayer.js reflectCompletion()
button.classList.add('d-none');
badge.classList.remove('d-none');
```

Neither the native `hidden` attribute nor `style.display` works here:
Bootstrap's Reboot emits `[hidden] { display: none !important }`, and an
author rule without `!important` cannot beat that — so do not reintroduce
either approach. Never rename/replace `.d-none` with another hide utility
on these two selectors without updating `LessonPlayer.js` in lockstep —
`classroom/lesson.blade.php` and every `classroom/partials/_*` template
(`_video`, `_text-image`, `_pdf`) depend on this exact class name.

## SPEC-26 `<x-course.card>`: 4 Sub-Components, One View Model, No Extra Query

The "Meus Cursos" card is a shell (`resources/views/components/course/
card.blade.php`) that forwards `$attributes` (the `dusk="course-card-{id}"`
selector) plus one `enrollment` prop into 3 sibling sub-components —
`card-header` (168px media band + status chip), `card-body` (org overline,
clamped title/summary, 10px progress bar), `card-footer` (contextual CTA
button + `"{N} aulas · {N}h · Prazo: ..."` caption). Every sub-component
reads its fields through `data_get($enrollment, '...')`, never `$enrollment
->course`, so the component tree works whether the controller hands a
plain array or an object, and every field defensively degrades (missing
`coverUrl` → pastel-wash gradient, `null` `ctaHref` → disabled button, no
`organization` → the literal fallback string `"Organização"`). Do not add
a 5th sub-component or inline the header/body/footer back into the shell —
each is unit-tested in isolation by
`tests/Feature/UiSortableFieldComponentsTest.php`'s sibling,
`tests/Browser/StudentCoursesCatalogUiTest.php`.

`card-footer`'s CTA button variant is derived from `displayStatus`, not
passed in by the controller: `concluido`/`expirado` render `tonal`,
`nao_iniciado`/`em_andamento` render `primary`; only `em_andamento` (and
only with a resolved `ctaHref`) shows the trailing `chevron-right` icon.
Keep that branching inside `card-footer.blade.php` — do not move it into
the controller's view model, which only supplies `ctaLabel`/`ctaHref`, not
button styling.

## SPEC-26 `resolveResumeLesson()`: One Algorithm, Two Callers

`StudentCourseController::resumeLessonFor()` is an **in-memory port** of
`Course::resumeLessonFor(User $user)` — it builds `$publishedLessons` and
`$progressRecords` from the already eager-loaded `modules`/
`modules.lessons`/`modules.lessons.progress` relations set up by
`index()`'s `with()` (see `learning-architecture`'s multi-org section for
why this must stay `withoutGlobalScope('org')`, not a fresh query per row)
instead of issuing Course's own DB queries — avoids an N+1 per enrollment
card. Both call sites delegate the actual tie-break rule to the single
static `Course::resolveResumeLesson()`. Never re-implement the
"most-recently-touched, or next-incomplete-if-already-done" rule at either
call site directly; extend `resolveResumeLesson()` so both stay in sync.
