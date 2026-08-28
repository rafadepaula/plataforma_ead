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
    - spec/specs/27-classroom-overview-and-progression.md
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
data carrying both `type=quiz` and `youtube_url` still 422s as quiz. The
video half of the guard tests the **resolved** `youtube_video_id`
accessor, never the raw `youtube_url` column:

```php
// complete(): rejects quiz lessons and PLAYABLE video lessons
if ($lesson->type === 'quiz' || $lesson->youtube_video_id !== null) {
    return response()->json(['message' => '...'], 422);
}

// updateProgress(): rejects quiz lessons and lessons with no resolvable player
if ($lesson->type === 'quiz' || $lesson->youtube_video_id === null) {
    return response()->json(['message' => '...'], 422);
}
```

Do not "simplify" either line back to `empty($lesson->youtube_url)`. A
lesson whose URL cannot be parsed into an id (a Vimeo link, a typo) has no
player, so no 90% threshold can ever fire for it; the accessor form is what
keeps it manually completable instead of permanently blocking course
progress. `LessonProgressControllerTest` pins both directions.

Both actions then apply **two** access checks, in this order:

```php
abort_unless($lesson->is_published, 404);   // no role exemption
$this->abortUnlessEnrolled($request, $lesson, $request->user());  // 403
```

The unpublished check no longer carries the `hasRole(RolesEnum::ALUNO)`
qualifier the page controllers use: staff never reach the write anyway,
because `abortUnlessEnrolled()` 403s anyone without an `active`/`completed`
`course_user` row. Keep that ordering — a draft lesson must read as 404,
not as 403 — and keep the enrollment guard on **every** new write endpoint
added to this controller. `lesson_progress` feeds
`RecalculateCourseProgress` and, through `CourseCompletedByStudent`,
`IssueCertificateAction`: letting a previewing Gestor write it would mint a
real Certificate for a Course they were never enrolled in.

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

On a route already behind `student.enrolled`, do **not** re-run the cascade:
`EnsureStudentIsEnrolled` stores the Course it resolved on the request, and
controllers read it back through the middleware's own constant, falling back
to the query only for a direct (unmiddlewared) controller call:

```php
$course = $request->attributes->get(EnsureStudentIsEnrolled::RESOLVED_COURSE_ATTRIBUTE);

if (! $course instanceof Course) {
    $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
}
```

This matters most on `updateProgress()`, which the player polls every 5s;
the fallback branch must stay, and must keep `withoutGlobalScopes()`.

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
on these two selectors without updating `LessonPlayer.js` in lockstep.
Since SPEC-28 the markup itself lives in exactly one file —
`resources/views/components/classroom/completion-bar.blade.php` — so this
rule has a single enforcement point, and
`tests/Feature/LessonDispatchOrderTest.php` asserts, on rendered HTML, that
neither control ever carries a ` hidden` attribute and that `isCompleted`
flips `.d-none` from the badge to the button.

## SPEC-28 `<x-classroom.completion-bar>`: One Emitter for Two Frozen Selectors

`resources/views/components/classroom/completion-bar.blade.php` takes **4**
props (`lesson`, `isCompleted` = false, `manual` = true,
`tracksProgress` = true) and a slot:

```blade
{{-- _pdf, _text-image --}}
<x-classroom.completion-bar :lesson="$lesson" :is-completed="$isCompleted ?? false"
    :tracks-progress="$tracksProgress ?? true" />

{{-- _video --}}
<x-classroom.completion-bar :lesson="$lesson" :is-completed="$isCompleted ?? false"
    :manual="false" :tracks-progress="$tracksProgress ?? true">
    <span class="small text-body-secondary" data-progress-hint>...</span>
</x-classroom.completion-bar>
```

The button renders only when `$manual && $tracksProgress`; the badge always
renders, so a previewing staff member still sees the lesson's real state.

- `:manual="false"` renders the badge **without** any button: a video
  lesson completes itself at the 90% threshold, and a manual button there
  would be rejected with 422 by `LessonProgressController::complete()`
  anyway (see the shape-guard section above).
- `:tracks-progress` must be forwarded by **every** partial, always as
  `$tracksProgress ?? true` so the component still works when rendered
  outside `ClassroomController::showLesson()`. Passing `false` (the actor
  has no `active`/`completed` enrollment) drops the button regardless of
  `:manual`, because `abortUnlessEnrolled()` would answer 403 to the click.
  Omitting it hands a previewing Admin/Gestor a button that always errors.
- `_quiz-placeholder` never mounts the component at all — a quiz completes
  through `SubmitQuizAttemptAction`.
- Extra layout is passed via `$attributes` (`class="justify-content-end
  mt-4"` in `_pdf`), never by wrapping the component in another flex row
  that duplicates the gap.

Do not re-inline this markup into a partial: `lesson-completed-badge` and
`mark-complete-button` are recorded per-file in
`tests/fixtures/dusk-selectors-snapshot.json`, so a second emitter breaks
`DuskSelectorContractTest` (and gives `LessonPlayer.js` two nodes to
toggle).

## SPEC-28 Lesson Partials: `.ds-*` Classes, Suffixed Media Selectors, Escaped Text

- The content card in `classroom/lesson.blade.php` is `card ds-surface
  border-0 shadow-sm ds-lesson-card` — **no `rounded-4`**. `_bridge.scss`
  redefines `$border-radius-xl: 28px`, so `rounded-4` would override the
  20px `.card` radius the design mandates. `.ds-lesson-card` exists because
  the Bootstrap spacer scale has no 32px step (`p-4` = 24px, `p-5` = 48px).
- Media wrappers use `.ds-ratio.ds-ratio-16x9` (+ `.ds-pdf-frame` on the
  PDF iframe), the degraded media state (video, PDF, image) uses `.ds-media-unavailable(-title
  /-text)` (neutral `--attention-container`, never a critical/red token),
  and the quiz hand-off uses `.ds-quiz-placeholder(-icon/-title/-text)` —
  not `border border-dashed`, which is a ghost class defined only in
  `_reorder-list.scss`.
- A lesson may carry several `lesson_media` rows. Both `_pdf` and
  `_text-image` compute one `$suffix` (`$index > 0 ? '-'.$index : ''`) and
  reuse it in every selector, so the **first** item keeps the unsuffixed
  `pdf-viewer-{id}` / `lesson-image-{id}` the E2E contract expects.
- `content_text` is rendered with `{{ }}` inside
  `dusk="lesson-content-{id}"` and shaped by `.ds-lesson-content`
  (`white-space: pre-wrap`) — never `{!! !!}`. Newlines are preserved by
  CSS, not by unescaping.
- A lesson with neither media nor text renders the calm
  `dusk="lesson-empty-{id}"` note plus the completion bar, never an empty
  card.

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

## SPEC-27 `<x-classroom.*>`: One Prop Per Value, State Comes From the Controller

The 5 classroom-overview components (`module`, `lesson-row`,
`next-lesson-card`, `progress-card`, `certificate-card`) each accept
exactly ONE prop per value, matching `ClassroomController::show()`'s frozen
view contract (see `learning-architecture`). Do not reintroduce the
defensive alias props they used to carry (`isCompleted`/`completed`,
`nextLesson`/`lesson`, `position`/`index`, five aliases for two progress
values) — an alias is a second source of truth that silently keeps working
after the controller stops sending one of the pair.

Consequences to preserve when editing them:

- `lesson-row` reads `$lesson->glyph` and the `completed` prop; it must
  NOT re-derive the icon with a `match(true)` over `youtube_url`/
  `pdf_path` — that path is media-blind (see `learning-architecture`).
- `show.blade.php` renders the `col-lg-8` main track FIRST and the
  `col-lg-4` sidebar SECOND, so the sidebar stacks below the track on
  <1024px with no CSS ordering trick. Never reorder the two columns.
- The `<li>` carries `dusk="lesson-{id}"` and the `<a>` inside it carries
  `dusk="open-lesson-{id}"`. This split is the E2E contract (SPEC-27 §3):
  never merge them onto one element.
- The empty state is emitted in exactly ONE place — the
  `<x-ui.empty-state dusk="no-modules">` branch in `show.blade.php`,
  which covers both "no modules at all" and "modules but no published
  lesson". `module.blade.php` keeps its own per-module caption for a
  module with zero published lessons; do not add a third copy.
- `certificate-card` renders a readable 12-char uppercase prefix of
  `validation_hash`, not the raw 64-char hash (which overflows the 4-col
  card). The full hash stays the only value used by the public
  verification flow (SPEC-09) — the prefix is display-only.

## SPEC-27 Chips Inside the Lesson Anchor: The Deliberate `<span>` Exception

`lesson-row.blade.php` renders its "Conteúdo"/"Prova" chip as a raw
`<span class="ds-chip ds-chip-outline|ds-chip-primary ds-chip-plain">`
instead of `<x-ui.chip>`. That is intentional and carries an in-file
comment: `<x-ui.chip>` renders a `<button>`, and interactive content
cannot legally be nested inside the row's wrapping `<a>`. A conventions
sweep that "componentizes" this back into `<x-ui.chip>` produces invalid
HTML and breaks the row click target. See `bootstrap-conventions` §3.
