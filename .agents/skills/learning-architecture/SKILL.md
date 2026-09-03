---
name: learning-architecture
description: >
  Student Learning & Progress domain: `lesson_progress` schema,
  completion-source rules per lesson type (video threshold / manual click /
  quiz), synchronous `LessonMarkedAsCompleted` -> `RecalculateCourseProgress`
  -> `CourseCompletedByStudent` event pipeline, `EnsureStudentIsEnrolled`
  multi-org access gate. Use when designing or reviewing feature writing
  `lesson_progress` or `course_user.progress_percentage`, before touching
  `MarkLessonCompleteAction`, or when deciding how student-facing route gets
  tenant/enrollment-gated.
license: MIT
metadata:
  feature: learning
  role: architecture
---

# Learning Architecture

## Overview

This domain covers Aluno-facing side of Courses: watching/reading
Lessons, marking them complete, resulting course-wide progress
recalculation. Never mutates `courses`/`modules`/`lessons` themselves. Only
writes `lesson_progress` and `course_user.progress_percentage`/`status`/
`completed_at`.

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `lesson_progress` | `user_id`, `lesson_id`, `is_completed`, `completion_source` (enum: `manual_click`\|`video_threshold`\|`quiz_passed`, nullable), `watched_seconds` (nullable), `completed_at` | **Cascade-inherited** via `lesson.module.course.org_id` — no own `org_id`, no `OrgScope` (see `tenancy-architecture`) |
| `course_user` (pivot, owned by the courses domain) | `progress_percentage`, `status`, `completed_at` | Written by this module listener, not owned by it |

`lesson_progress` has unique `(user_id, lesson_id)` constraint. One row per
student per lesson, upserted via `firstOrNew()`, never inserted twice.

## The Completion-Source Rule Per Lesson Type

No single "mark complete" button. Trigger and `completion_source` value
differ by lesson shape:

| Lesson shape | Trigger | `completion_source` | Endpoint |
| --- | --- | --- | --- |
| Video (`video_url` set, id resolves — YouTube or Vimeo) | provider adapter polling reports `watched_seconds` ≥ 90% of `duration_seconds` | `video_threshold` | `POST /lessons/{lesson}/progress` |
| Text/PDF/Image (no `video_url`) | Explicit "Marcar como concluída" click | `manual_click` | `POST /lessons/{lesson}/complete` |
| Quiz (`type = quiz`) | `SubmitQuizAttemptAction` when `quiz_attempts.is_passed = true` | `quiz_passed` | none — no manual button ever renders for quiz lesson |

Both HTTP endpoints reject wrong shape with 422, never silently accept it:
`complete()` 422s on `type=quiz` or playable video (`hasPlayableVideo()`);
`updateProgress()` 422s on `type=quiz` or non-playable lesson. See
`learning-conventions` for exact controller checks.

## `MarkLessonCompleteAction`: the Single Write Path

`app/Actions/MarkLessonCompleteAction.php` is only place any
`lesson_progress` row gets written. Shared today by
`LessonProgressController` two endpoints and, per its own docblock, meant
for `SubmitQuizAttemptAction` too. Contract:

- **Idempotent**: once `is_completed = true`, calling again never flips it
  back to `false`, never re-sets `completed_at`, never re-dispatches
  `LessonMarkedAsCompleted`.
- **`watched_seconds` never decreases**: persisted as `GREATEST(current,
  reported)`. Passing `null` (manual completions) leaves stored value
  untouched.
- **Event dispatch is transition-gated**: `LessonMarkedAsCompleted` fires
  only on `false` -> `true` transition, never on repeat call. Keeps
  `RecalculateCourseProgress` from redundantly recomputing on every idle
  video-progress poll below threshold.

## The Progress Pipeline: `LessonMarkedAsCompleted` -> `RecalculateCourseProgress` -> `CourseCompletedByStudent`

Recalculation is **synchronous**, same request
(`QUEUE_CONNECTION=sync` default). No queued job in this pipeline.
`RecalculateCourseProgress::handle()` auto-discovered purely from its
`handle(LessonMarkedAsCompleted $event)` type-hint (no explicit
`EventServiceProvider` registration to keep in sync).

Formula (equal weight per lesson, ignoring module/workload-hours):

```
progress_percentage = ROUND(completed_published_lessons / total_published_lessons * 100)
```

- Denominator: `Course::publishedLessonsCountFor()` — only
  `lessons.is_published = true`. `Module`/`Lesson` carry `SoftDeletes`, so
  soft-deleted module/lesson excluded automatically by its own global scope
  (no explicit `whereNull('deleted_at')` needed). Course with zero published
  lessons yields `0`, not division error (`$totalPublishedLessons > 0`
  guard).
- Numerator: `Course::completedLessonsCountFor(User $user)` — same
  published/non-deleted filter, joined through
  `lesson_progress.is_completed`.
- When resulting percentage reaches `required_percentage` of Course
  `course_completion_rules` row where `rule_type = 'all_lessons'`, listener
  also flips `course_user.status` to `completed`,
  stamps `completed_at`, dispatches `CourseCompletedByStudent` — event the
  certificates domain listens for. No rule row for course means no
  auto-completion, no matter how high percentage climbs.

Course resolution inside listener
(`$event->lesson->module->course()->withoutGlobalScopes()->firstOrFail()`)
and every controller/middleware Course lookup in this module deliberately
bypass `OrgScope` — see "Multi-Org Student Access" below for why.

## Multi-Org Student Access & `EnsureStudentIsEnrolled`

Aluno is not scoped to one Organization. "Meus Cursos" lists enrollments
across every org where student holds `active`/`completed` `course_user` row
(`User::courses()`), and classroom resolves org from Course being viewed,
not from logged-in user own `org_id` (Aluno has none). So every Course
lookup in this module controllers/middleware/listener uses
`Course::query()->withoutGlobalScopes()` or
`->course()->withoutGlobalScopes()->firstOrFail()`, never typed `Course
$course` implicit route binding or scoped relation call. Latter silently
returns nothing for org-less Aluno, turning intended "show the course" into
false 404/`null`.

`EnsureStudentIsEnrolled` (registered as `student.enrolled` route
middleware alias in `bootstrap/app.php`) gates every
classroom/lesson/progress route:

- **Admin**: always allowed. Guard is about enrollment, not tenant
  management, so no active-impersonation requirement applies here.
- **Gestor**: allowed only when own `org_id` matches resolved Course
  `org_id`.
- **Aluno**: allowed only with `course_user` row in `active` **or**
  `completed` status (`User::hasActiveOrCompletedEnrollment()`).
  `cancelled` enrollment, or no enrollment row at all, is denied.

Denial shape depends on what the request can consume, and the two are NOT
interchangeable: an Aluno page request is `redirect()->route(
'student.courses.index')` flashing `error` = "Acesso negado. Você não
possui matrícula ativa neste curso.", while a request that
`expectsJson()` (the lesson progress/complete endpoints) still gets a bare
`abort(403)` because it has nowhere to redirect to. Gestor cross-org
denial stays a plain 403 in every case — that is tenancy, not enrollment.

Middleware resolves Course from either `{course}` or `{lesson}` route
parameter (supports both route shapes registered in `routes/web.php`),
always `withoutGlobalScopes()`, same reason as above. It publishes that
resolved Course on the request under
`EnsureStudentIsEnrolled::RESOLVED_COURSE_ATTRIBUTE`, so a controller on
the same route reads it back instead of walking `lesson -> module ->
course` again (see `learning-conventions`). The constant is a **public
contract**: renaming it, or dropping the `$request->attributes->set()`
call, silently pushes `LessonProgressController` onto its fallback query on
every 5-second progress poll.

**This middleware is not the whole gate for progress writes.** Passing it
means "may open the screen", not "may record progress": Admin and same-org
Gestor are allowed through so they can preview a course, while
`LessonProgressController::abortUnlessEnrolled()` answers 403 to both write
endpoints for anyone without an `active`/`completed` `course_user` row. The
view side of that split is the `tracksProgress` flag described under the
unified lesson player below. Any new endpoint that writes `lesson_progress` needs the
second check too — `student.enrolled` alone will let staff through.

## "Meus Cursos" Catalog Helpers on `Course`

The "Meus Cursos" catalog (`StudentCourseController` +
`student.courses.index`) was rebuilt as a tabbed grid of
rich cards. That work added no new table — only read helpers on `Course`
(owned here, alongside `publishedLessonsCountFor()`/`completedLessonsCountFor()`,
because they serve this module's student-facing read path, not the
courses domain's Gestor CRUD) and a `course_user.expires_at` column
(schema itself owned by `courses-architecture`, since `course_user` is a
courses-domain table):

- **`Course::publishedLessonsInOrder(): Collection`** (private) — this
  Course's published Lessons through non-soft-deleted Modules, ordered
  Module `order_index` then Lesson `order_index`. Backs the next three
  methods.
- **`Course::firstPublishedLessonFor(): ?Lesson`** — earliest published
  Lesson, or `null` when the Course has none yet (every Lesson still a
  draft, or all soft-deleted). Drives the `nao_iniciado` → "Começar curso"
  CTA.
- **`Course::resumeLessonFor(User $user): ?Lesson`** — the "Continuar" CTA
  target: no `lesson_progress` yet → first published Lesson; otherwise the
  most-recently-touched Lesson, UNLESS it is already completed, in which
  case the next not-yet-completed Lesson after it (falling back to the
  last-touched Lesson itself when everything after it is also done).
- **`Course::resolveResumeLesson(Collection $publishedLessons, Collection
  $progressRecords): ?Lesson`** (`static`) — the pure ordering/tie-break
  algorithm behind `resumeLessonFor()`, factored out so
  `StudentCourseController` can share the exact same rule from an
  in-memory port (see `learning-conventions`) instead of re-implementing
  it against already eager-loaded relations. Never duplicate this
  tie-break logic at a second call site — extend this one method.
- **`Course::enrollmentDisplayStatusFor(object $pivot): string`** — derives
  one of 4 card states (`nao_iniciado`\|`em_andamento`\|`concluido`\|`expirado`)
  from a `course_user` pivot row (real pivot model or any `object` with
  the same 4 fields, so callers can compose it from an eager-loaded row
  with no extra query). `completed` pivot status always wins as
  `concluido`, even past `expires_at` — finishing before the deadline
  never regresses to "expired" after the fact. An `active` pivot past a
  set `expires_at` is `expirado` regardless of progress. Otherwise
  `active` is `nao_iniciado`/`em_andamento` by whether progress is zero.
  `expirado` is a **read** of an `active` row, never a 5th `course_user
  .status` enum value.

## Card View-Model Contract (`StudentCourseController` → `x-course.card`)

`StudentCourseController::index()` groups the Aluno's `active`/`completed`
enrollments (never `cancelled`, see the multi-org section above) into 3
tabs by the **raw pivot `status`**, not the derived display status —
`em_andamento` tab is every `active` row regardless of whether its chip
reads `nao_iniciado`/`em_andamento`/`expirado`; `concluidos` is every
`completed` row; `todos` is both. Each row becomes one plain `object` view
model (`course`, `organization`, `pivotStatus`, `displayStatus`,
`progressPercentage`, `ctaLabel`, `ctaHref`, `secondaryCtaLabel`,
`secondaryCtaHref`, `lessonsCount`, `workloadHours`, `deadlineLabel`)
consumed by `<x-course.card>` and its 3
sub-components (`card-header`/`card-body`/`card-footer`). Three rules any
change to this pipeline must preserve:

- **A `concluido` row ALWAYS resolves the classroom CTA**: its primary CTA
  is `["Ver sala de aula", route('classroom.show', $course)]` — never the
  certificate. `EnsureStudentIsEnrolled` and the forum policies admit a
  `completed` pivot, so a finished student must keep a clickable path back
  into the content and the full forum. The certificate travels on the row's
  secondary CTA slot instead ("Baixar certificado" link once issued, the
  neutral "Certificado em emissão" placeholder with `secondaryCtaHref =
  null` while it hasn't).
- **CTA degrades to `null`, never a 404 link**: a course with zero
  published Lessons gets `ctaHref = null` — `<x-course.card-footer>`
  renders a disabled button instead of linking to a route that would
  404/403; a secondary slot with a label but `null` href degrades to plain
  text, never a dead link.
- **2% visual progress floor**: any row that is not `nao_iniciado` shows
  at least a 2% bar even at 0 real progress (e.g. an `expirado` row the
  student never started), so the bar never reads as a rendering bug. A
  genuinely `nao_iniciado` row still shows a true 0%.

## Classroom Overview View Contract (`ClassroomController::show()`)

`GET courses/{course}/classroom` (`auth` + `student.enrolled`) hands
`classroom.show` a **frozen, normalized** set of 7 keys — nothing else, and
no alias duplicates:

`course`, `modules`, `progressPercentage`, `completedLessonsCount`,
`totalLessonsCount`, `certificate`, `nextLesson`.

Per-item state travels **on the models**, so the Blade layer never performs a
lookup or resolves media itself:

- Each `Module` carries `completed_lessons_count` / `total_lessons_count`.
- Each `Lesson` carries `is_completed` (bool) and `glyph` (string).
- `progressPercentage` is read-only from `course_user.progress_percentage`
  (the pivot the `RecalculateCourseProgress` listener writes). Never
  recompute it in the controller, the view, or JS. An Admin/Gestor preview
  has no pivot row at all: it falls back to `0`, and the progress card must
  still render `0%` rather than blow up.

### Glyph resolution is media-aware (`Lesson::pendingGlyph`)

The pending-state glyph is resolved in PHP, never in Blade:
`type === 'quiz'` → `clipboard`; `video_url` filled → `play`;
`hasPdfAttachment()` → `file-text`; else `book-open`. A completed lesson
always overrides to `check`.

`hasPdfAttachment()` is the media-aware half: since the `lesson_media`
migration, a PDF may exist ONLY as a `lesson_media` row of kind `pdf`, with
the deprecated flat `lessons.pdf_path` column empty. Testing `pdf_path`
alone (as the legacy classroom Blade did) mis-renders such a lesson as
`book-open`. `show()` eager-loads `lessons.media` in the SAME `with()` call
so the check stays N+1-free across every row of the track.

### A revoked certificate is resolved, not hidden

`show()` looks the certificate up by `user_id` + `course_id` **without** a
`whereNull('revoked_at')` filter. Revocation is logical and terminal (see
`certificates-architecture`), so a revoked record must stay resolvable and
distinguishable from "never issued". The card branches on
`Certificate::isRevoked()`: a revoked certificate renders the neutral
`certificate-unavailable` surface with revocation-specific wording and
**never** links `certificates.download`. Filtering it out in the query
would silently regress it into the generic "not yet issued" copy.

## Unified Lesson Player — One Card, One Format, Exclusive Dispatch

`GET lessons/{lesson}` (`ClassroomController::showLesson`, `auth` +
`student.enrolled`) hands `classroom.lesson` **6 keys** — `lesson`,
`course`, `isCompleted`, `watchedSeconds`, `tracksProgress`,
`mediaAvailability` — and the Blade layer picks **exactly one** media
format from a frozen `@if/@elseif` chain in
`resources/views/classroom/lesson.blade.php`:

```
type === 'quiz'        -> classroom.partials._quiz-placeholder
filled(video_url)      -> classroom.partials._video
filled(pdf_path)       -> classroom.partials._pdf
else                   -> classroom.partials._text-image
```

The order is a **contract, not a style choice**. Rows carry conflicting
content columns in the wild (a quiz Lesson that also stores a
`video_url`, a video Lesson that also stores a `pdf_path`), and this
chain is what guarantees a quiz never reaches the video player and never
renders a manual-completion button. `LessonProgressController::
updateProgress()` mirrors the same precedence server-side — it checks
`type === 'quiz'` **before** the `hasPlayableVideo()` check — so the
two layers agree on which lesson is video-driven. Reordering either side
without the other silently lets a quiz be completed by a video poll.
`tests/Feature/LessonDispatchOrderTest.php` freezes all four branches.

### The player is click-to-load, provider-agnostic (`lesson-player/`)

`_video.blade.php` NEVER renders a provider iframe. Server ships one
`.ds-player` shell (`dusk="video-player-{id}"` on BOTH the playable and the
unavailable branch): pastel-wash facade button
(`dusk="video-facade-{id}"`), a server-rendered control bar
(`dusk="video-play/seek/time/mute/volume/fullscreen-{id}"`, `.d-none` + inert
until boot — selectors live in Blade, so the Dusk snapshot sees them), and the
runtime-error notice. Wiring travels as data attributes: `data-provider`
(`youtube`\|`vimeo`), `data-video-id`, `data-video-embed` (canonical
nocookie/`player.vimeo.com` URL, carrying `?h=` for unlisted Vimeo),
`data-lesson-id`, `data-progress-url`.

First facade click boots a `VideoPlayerAdapter`
(`resources/js/modules/lesson-player/`): `YoutubeAdapter` (IFrame API,
`youtube-nocookie.com` host, `controls: 0`, `disablekb: 1`) or `VimeoAdapter`
(Player SDK via CDN, `controls: false`, `dnt`), same surface:
`play/pause/seek/setVolume/setMuted/getCurrentTime/getDuration/getState/on`,
events `ready/timeupdate/statechange/error`. `PlayerController` owns the
overlay controls, keyboard shortcuts, fullscreen, auto-hide and the 5s poll
that funnels through `LessonPlayer.reportProgress`. Zero third-party JS loads
before the student clicks; adapter `error` swaps the shell to the neutral
unavailable notice (runtime-degraded video, provider removed it).

The server-side predicate is the **resolved video id** (`Lesson::
hasPlayableVideo()`), not the raw `video_url` column. A lesson whose URL cannot be parsed into an id has no
player to drive the 90% threshold, so `complete()` accepts it and
`updateProgress()` rejects it — that inversion is what keeps one broken
link from freezing a whole course. `_video.blade.php` mirrors it by passing
`:manual="$videoId === null"`.

### The two view flags added with the media states

- **`tracksProgress`** — `$user->hasActiveOrCompletedEnrollment($course)`.
  Every partial reads it (`$tracksProgress ?? true`) and forwards it to the
  completion bar; `_video` also gates the `data-progress-url` polling wiring on
  it (the player shell itself still renders for preview — see below). It exists because `EnsureStudentIsEnrolled` lets Admin and same-org
  Gestor *open* the screen to preview it, while
  `LessonProgressController::abortUnlessEnrolled()` answers **403** on both
  write endpoints for anyone without an `active`/`completed` `course_user`
  row. Without the flag a previewing staff member would see a button that
  always errors, and a video preview would queue an error toast every 5s.
- **`mediaAvailability`** — a `path => bool` map built once per request by
  `ClassroomController::resolveMediaAvailability()`, covering only the
  files the dispatched format will actually draw. `_pdf` and `_text-image`
  read it to render the neutral `.ds-media-unavailable` notice instead of
  an empty viewer or a broken-image icon. Views must never touch `Storage`
  themselves: on a remote disk that is one network round-trip per file per
  render.

A fifth partial must accept and forward **both** flags, or it silently
renders a wrong screen.

### The completion bar is one shared component, never re-inlined

`resources/views/components/classroom/completion-bar.blade.php`
(`<x-classroom.completion-bar :lesson :is-completed :manual :tracks-progress>`)
is the ONLY
place `dusk="lesson-completed-badge"` and `dusk="mark-complete-button"` are
emitted. `_video` passes `:manual="false"` (video completes itself at 90%,
so no button exists at all); `_pdf` and `_text-image` take the default
`true`; `:tracks-progress="false"` drops the button for a non-enrolled
previewer regardless of `:manual`. Do not copy the markup back into a
partial — the selectors are E2E
contract, and `tests/fixtures/dusk-selectors-snapshot.json` records the
file each one lives in.

Visibility of both controls is expressed **only** through `.d-none`
(`@class()` server-side, `classList` in `LessonPlayer.js`). The `hidden`
attribute is prohibited on them — Reboot's
`[hidden] { display: none !important; }` cannot be beaten by a class
toggle. See `learning-conventions` for the rule and
`LessonDispatchOrderTest` for the guardrail.

## Related

- `courses-architecture` — `courses`/`modules`/`lessons` schema and
  `OrgScope`/cascade-inheritance model this feature reads from.
- `tenancy-architecture` — `OrgScope` trait and `withoutGlobalScopes()`
  cascade pattern this module relies on throughout.
- `quizzes-architecture` — future `quiz_passed` completion source and
  `SubmitQuizAttemptAction`, which reuses `MarkLessonCompleteAction`.
- `certificates-architecture` — listens for `CourseCompletedByStudent`.
