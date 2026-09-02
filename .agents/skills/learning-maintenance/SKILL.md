---
name: learning-maintenance
description: >
  Debug, test, edge-case guide for Student Learning & Progress:
  mandatory PHPUnit/Dusk test files, common
  `progress_percentage`/`EnsureStudentIsEnrolled` failure modes,
  frontend-build gotcha for `LessonPlayer.js`. Use when
  `MultiOrgStudentClassroomTest`, `EnsureStudentIsEnrolledTest`,
  `CourseProgressCalculationTest`, or `VideoThresholdCompletionTest`
  fail; progress not recalculating; or video threshold not auto-completing
  lesson.
license: MIT
metadata:
  feature: learning
  role: maintenance
---

# Learning Maintenance

## Mandatory Test Coverage for This Module

Tests guard this module's contract. Must stay green (PHPUnit, no Pest):

- `tests/Unit/Actions/MarkLessonCompleteActionTest.php` — idempotent
  completion, `GREATEST(watched_seconds)`, `completed_at` set only on
  first completion, `LessonMarkedAsCompleted` dispatched only on `false`
  -> `true` transition.
- `tests/Unit/Listeners/RecalculateCourseProgressTest.php` — percentage
  math (including zero-published-lessons guard),
  `course_completion_rules`-driven auto-completion,
  `CourseCompletedByStudent` dispatch.
- `tests/Feature/EnsureStudentIsEnrolledTest.php` — 10 tests: Admin always
  allowed, Gestor gated on `org_id` match (403 across orgs), Aluno gated on
  `active`/`completed` `course_user` status. A `cancelled` row or no row at
  all redirects the Aluno to `student.courses.index` flashing `error` =
  "Acesso negado. Você não possui matrícula ativa neste curso.", asserted
  both on the redirect and on the rendered catalog page; a JSON/AJAX
  request keeps the bare 403.
- `tests/Feature/CourseProgressCalculationTest.php` — end-to-end through
  real event/listener pipeline (`QUEUE_CONNECTION=sync`).
- `tests/Feature/MultiOrgStudentClassroomTest.php` — 21 tests. Aluno "Meus
  Cursos" list enrollments across multiple Organizations; classroom access
  resolve org from Course, not from (org-less) Aluno. It also
  owns the classroom **view DATA** contract (the rendered markup lives in
  `ClassroomOverviewRenderingTest`): the 7 frozen keys plus
  `assertArrayNotHasKey` on the three dropped aliases
  (`completedLessonIds`, `completedCount`, `totalLessons`), per-lesson
  `is_completed`/`glyph` resolution including a `lesson_media`-only PDF,
  the revoked-certificate model reaching the view unfiltered, and a
  `DB::listen` query-count guard comparing a 1×1 Course against a 4×5 one
  (warm up with one request first — the Spatie permission cache skews the
  first hit). That guard is what fails if `lessons.media` leaves the
  `with()` call.
- `tests/Feature/LessonManualCompletionTest.php` — `POST
  /lessons/{lesson}/complete` 422 for quiz/video lessons, succeed for
  text/PDF/image.
- `tests/Feature/VideoThresholdCompletionTest.php` (Feature) — `POST
  /lessons/{lesson}/progress` persist sub-threshold `watched_seconds`
  without completing, auto-complete at/above 90%.
- `tests/Browser/MultiOrgStudentClassroomTest.php`,
  `tests/Browser/LessonPlayerDuskTest.php` (Dusk E2E) — full browser flow;
  the video threshold case in `LessonPlayerDuskTest`
  (`test_video_lesson_shows_the_player_shell_and_auto_completes_at_the_threshold`)
  drive `window.LessonPlayer.reportProgress()` directly rather than real
  YouTube embed.
- `tests/Feature/StudentCourseControllerTest.php` — 19 tests:
  multi-org enrollment aggregation (including duplicate course titles
  across orgs resolving to the right org per card, N+1-free), all 3 tabs
  filtered by raw pivot `status` (never derived `displayStatus`), tab
  badge counts, a `cancelled` enrollment never appearing in any tab, all 4
  derived statuses (`concluido` winning over a past `expires_at`
  included), the 2%-visual-minimum floor on an `expirado` row with zero
  real progress, certificate-issued vs certificate-not-yet-issued CTA
  degradation, a zero-published-lesson course not crashing, soft-deleted
  Course exclusion, an unpublished-but-enrolled Course still rendering
  read-only, and a `role:gestor` 403.
- `tests/Feature/ClassroomOverviewRenderingTest.php` — 18 tests
  asserting the RENDERED MARKUP of the classroom overview, which
  `MultiOrgStudentClassroomTest` (view DATA only) never touches: verbatim
  header copy (`Sala de aula`, the subtitle, the `Meus cursos` breadcrumb,
  the `Fórum do curso` link target), the neutral `Certificado ainda não
  disponível` surface with no download link, the `dusk="lesson-{id}"`-on-
  `<li>` vs `dusk="open-lesson-{id}"`-on-`<a>` split, the completed-lesson
  selector, the `Conteúdo`/`Prova` chip variants, the `no-modules` empty
  state (both the module-less Course and the modules-but-no-published-
  lessons Course), the `col-lg-8`-before-`col-lg-4` byte order in the
  response body, the completed-course-without-completion-rules neutral
  surface, the issued-certificate download CTA, the issued-certificate
  card's 12-char uppercase code block plus its `certificates.verify` link
  (and their joint absence while the certificate is unavailable), the
  revoked-certificate copy, the staff-preview (null pivot) zero-progress
  path, a lesson unpublished after completion, the singular completion
  caption, and the real partial counts.
- `tests/Browser/ClassroomOverviewDuskTest.php` (Dusk) — 9 tests:
  the 0% → 33% → 100% lifecycle with certificate issuance, the staff
  preview (Admin and owning Gestor at 0%, foreign Gestor 403), the
  `no-modules` empty state, the issued-certificate `href` pointing at
  `certificates.download` for the OWNING student, a revoked certificate
  showing `@certificate-unavailable` with NO `@download-certificate`,
  next-lesson navigation plus its `assertMissing` at 100%, quiz-vs-content
  glyph/chip rendering, a 375px stacking check asserting the sidebar sits
  below the track with no horizontal overflow, and a long-title overflow
  check.
- `tests/Feature/LessonDispatchOrderTest.php` — 9 tests freezing
  the lesson-player view contract: the exclusive `@if/@elseif` dispatch on
  conflicting rows (a `type=quiz` Lesson carrying BOTH `youtube_url` and
  `pdf_path` renders `quiz-placeholder` and neither `video-player-{id}`
  nor `mark-complete-button`; a content Lesson with both renders only the
  video), the PDF-only and text-only branches, the material-less lesson
  falling back to `lesson-empty-{id}` plus the completion bar, the
  `hidden`-attribute prohibition asserted on the rendered tags of both
  completion controls, the `.d-none` swap in both directions (pending vs a
  completed `lesson_progress` row), and `content_text` escaping
  (`<script>alert(1)</script>` + newlines).
- `tests/Browser/LessonPlayerDuskTest.php` (Dusk) — one lifecycle
  test covering all 6 rendered states (video, PDF, text/image, quiz ready,
  quiz in preparation, degraded video), the back-to-classroom button and
  the resulting `lesson_progress` rows.
- `tests/Browser/StudentCoursesCatalogUiTest.php` (Dusk) — the
  tabs/cards lifecycle chain (all 3 tabs, all 4 status chips, progress bar
  selector, per-status CTA target) plus a separate contextual-empty-state-
  per-tab test.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=MarkLessonCompleteActionTest
vendor/bin/sail artisan test --filter=RecalculateCourseProgressTest
vendor/bin/sail artisan test --filter=EnsureStudentIsEnrolledTest
vendor/bin/sail artisan test --filter=CourseProgressCalculationTest
vendor/bin/sail artisan test --filter=MultiOrgStudentClassroomTest
vendor/bin/sail artisan test --filter=LessonManualCompletionTest
vendor/bin/sail artisan test --filter=VideoThresholdCompletionTest
vendor/bin/sail artisan test --filter=StudentCourseControllerTest
vendor/bin/sail artisan test --filter=ClassroomOverviewRenderingTest
vendor/bin/sail artisan test --filter=LessonDispatchOrderTest
vendor/bin/sail artisan test --filter=LessonProgressControllerTest
vendor/bin/sail artisan test --filter=LessonMediaTest
vendor/bin/sail artisan test --filter=DuskSelectorContractTest
vendor/bin/sail artisan dusk --filter=MultiOrgStudentClassroomTest
vendor/bin/sail artisan dusk --filter=StudentCoursesCatalogUiTest
vendor/bin/sail dusk tests/Browser/ClassroomOverviewDuskTest.php
vendor/bin/sail artisan dusk tests/Browser/LessonPlayerDuskTest.php
```

## `progress_percentage` Not Updating

Check, in order:

1. `QUEUE_CONNECTION` — must resolve to `sync` (project default) or
   `RecalculateCourseProgress` never run inline within test/request.
2. Is `MarkLessonCompleteAction::execute()` actually completing
   transition (`false` -> `true`)? Repeat call on already-completed
   lesson never redispatch `LessonMarkedAsCompleted`, so recalculation
   correctly not re-run — expected, not bug.
3. Is lesson `is_published = true`? Unpublished lessons excluded from
   both `publishedLessonsCountFor()` denominator and
   `completedLessonsCountFor()` numerator — completing unpublished lesson
   still write `lesson_progress` but cannot move percentage.
4. Is there `course_completion_rules` row with `rule_type =
   'all_lessons'` for course? Without one, course never auto-transition
   to `completed` no matter how high percentage climb — correct by design,
   not bug in listener.

## `EnsureStudentIsEnrolled` Returning the Wrong Status

- 403 for Gestor: confirm `$user->org_id` match resolved Course
  `org_id` — middleware compare ints (`(int)` cast on both sides), so
  string/int mismatch from stale cast elsewhere is not cause; check
  Course actually resolved `withoutGlobalScopes()` and not silently 404'd
  first.
- Redirect instead of 403 for Aluno with what look like valid enrollment:
  check `course_user.status` value directly — only `active` and
  `completed` pass `User::hasActiveOrCompletedEnrollment()`; `cancelled`
  row (e.g. after `EnrollmentController::destroy()`) is deliberate denial,
  not bug. Denied Aluno page request never render 403 page: it
  `redirect()->route('student.courses.index')` flashing `error` =
  "Acesso negado. Você não possui matrícula ativa neste curso." Only
  `$request->expectsJson()` request (lesson progress/complete endpoints)
  still `abort(403)`, because redirect useless to them. Gestor cross-org
  denial stay a plain 403 — it is tenancy, not enrollment.
- 404 instead of expected 403: middleware `resolveCourse()` use
  `findOrFail()`/`firstOrFail()` on `withoutGlobalScopes()` query — if
  that still 404, Course/Lesson genuinely not exist (soft-deleted or bad
  ID), which is correct; 404 here is never this middleware silently
  mis-scoping.

## Frontend Build Staleness

If `window.LessonPlayer` is `undefined` in browser (video polling/manual
completion silently do nothing, Dusk tests time out waiting on player
state), `public/build` stale relative to
`resources/js/modules/LessonPlayer.js`/`resources/js/app.js` — run
`vendor/bin/sail npm run build` — never `npm run dev`/`composer run dev`,
which leave `public/hot` behind and break every Dusk run (see
`laravel-dusk`) — before re-running Dusk.

## Course Catalog Dusk Gotchas

- **Selector rename**: the pre-rebuild catalog used
  `org-group-{id}`/`student-course-{id}`/`open-classroom-{id}`. These are
  retired — current selectors are `course-card-{id}`, `course-status-
  {id}`, `course-progress-{id}`, `course-continue-{id}` (on the
  `<x-course.card>` family; see `learning-conventions`). If a Dusk test
  still references the old names it is asserting against the pre-refactor
  layout — update it, and update
  `tests/fixtures/dusk-selectors-snapshot.json` in the same change
  (`DuskSelectorContractTest` fails the frozen-selector-baseline guardrail
  otherwise).
- **`.kicker` is uppercase**: `card-body`'s organization overline uses the
  existing `.kicker` class, whose `text-transform: uppercase` makes a
  literal-case `assertSee('Organização A')` fail even though the text is
  correct. Use `assertSeeIgnoringCase()` against anything rendered inside
  `.kicker`.
- **Card CTA disabled state**: when `ctaHref` is `null` (see
  `learning-architecture`'s CTA-degrades-to-null rule), `course-continue-
  {id}` renders a disabled `<x-ui.button>`, not a missing element — assert
  `disabled`, don't assert the selector is absent.

## Auto-Update Protocol

Any
change to `MarkLessonCompleteAction`, `RecalculateCourseProgress`,
`EnsureStudentIsEnrolled`, `LessonMarkedAsCompleted`/
`CourseCompletedByStudent` events, `StudentCourseController`/
`ClassroomController`/`LessonProgressController`, the `x-course.*`
component family, `resources/scss/components/_course-card.scss`,
`student.enrolled`/`role:aluno` routes, `lesson_progress`/`course_user`
schema, or `LessonPlayer.js` **must** update all three learning skills
(`learning-architecture`, `learning-conventions`, `learning-maintenance`)
in same change, before task considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affect what reviewer must
  check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fail build if
  any of three `learning-*` skills missing.

## Related

- `courses-maintenance` — analogous module this one read Course/Module/
  Lesson data from; mirror its `withoutGlobalScopes()` cross-tenant-lookup
  convention.
- `tenancy-maintenance` — underlying `OrgScope` contract this module
  deliberately bypass throughout.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
maintain this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.

## Classroom Overview Failure Modes

- **A completed lesson renders no check / `@lesson-completed-{id}` is
  missing**: `ClassroomController::show()` sets `is_completed` on each
  `Lesson`; if it stops doing so (or the view goes back to an
  `in_array($lesson->id, $completedLessonIds, true)` lookup) every row
  falls back to pending while the module caption — which counts with a
  loose `whereIn` — still reads "1 de 2 aulas concluídas". That
  disagreement between the caption and the rows is the tell.
- **A PDF lesson shows `book-open` instead of `file-text`**: its PDF lives
  only in `lesson_media`, and something is testing the deprecated flat
  `lessons.pdf_path` column again. Fix it in `Lesson::hasPdfAttachment()`,
  never in Blade, and keep `lessons.media` in `show()`'s `with()` call or
  the check becomes an N+1 across the whole track.
- **A revoked certificate reads as "never issued"**: the certificate
  lookup grew a `whereNull('revoked_at')` filter back. Resolve the record
  and branch on `isRevoked()` instead (see `learning-architecture`).
- **`@download-certificate` 403s in the browser**: `certificates.download`
  is staff-or-owner; the classroom card must link the certificate whose
  `user_id` is the acting student. Authorization itself is covered by
  `tests/Feature/CertificateControllerTest.php` — do not duplicate it in
  the Dusk test, which only asserts the `href` target (clicking it starts
  a PDF file download and leaves the page in place).
- **The sidebar renders above the track on mobile**: someone reordered the
  two `row` children. The order is DOM-level, not CSS-level; the 375px
  Dusk check compares `getBoundingClientRect().top` of `@module-{id}` and
  `@course-progress-bar` and will catch it.

## Lesson Player Failure Modes

- **`DuskSelectorContractTest` fails with a count mismatch after touching
  the lesson screen**: `lesson-completed-badge`/`mark-complete-button` now
  live in `components/classroom/completion-bar.blade.php`, not in the three
  partials, and `_pdf`/`_text-image` emit their suffixed selectors through
  a shared `$suffix` variable. The fixture records the FILE of every
  selector, so any move — even with identical selector text — must be
  written back to `tests/fixtures/dusk-selectors-snapshot.json` in the same
  change. Regenerate by scanning `resources/views/**/*.blade.php` for
  `dusk="([^"]*)"`, sorting by `file` then `selector`, and rewriting
  `count` + `entries`.
- **The "Concluída" badge stays invisible after the button is clicked (or
  after a 90% video poll)**: something reintroduced the `hidden` attribute
  on the completion controls. Reboot's `[hidden] { display: none
  !important }` beats the class toggle `LessonPlayer.js` performs.
  `LessonDispatchOrderTest` asserts this on rendered HTML — if that test is
  green and the browser still misbehaves, `public/build` is stale.
- **A quiz lesson renders a video player or a manual-completion button**:
  the `@if/@elseif` chain in `classroom/lesson.blade.php` was reordered, or
  a partial mounted `<x-classroom.completion-bar>` without
  `:manual="false"`. The server-side twin of this rule is the `type ===
  'quiz'` check that must stay FIRST in `LessonProgressController::
  updateProgress()`.
- **A second PDF/image steals the unsuffixed selector**: the `$suffix`
  computation must stay `$index > 0 ? '-'.$index : ''` and be used in every
  selector of the loop. Re-computing it inline in one attribute and not the
  other is how `pdf-viewer-{id}` and `pdf-download-{id}` drift apart.
- **Newlines in `content_text` collapse**: they are preserved by
  `.ds-lesson-content`'s `white-space: pre-wrap`, never by switching `{{ }}`
  to `{!! !!}`. If the SCSS class is dropped, fix the SCSS.

- **A previewing Admin/Gestor gets 403 from `lessons.complete` or the
  progress poll**: that is the designed behaviour, not a regression.
  `student.enrolled` lets staff *open* the lesson;
  `LessonProgressController::abortUnlessEnrolled()` refuses the *write*, so
  a preview can never mint a Certificate. The screen should not have
  offered the button in the first place — check that the partial forwards
  `:tracks-progress="$tracksProgress ?? true"`. A missing forward is the
  actual bug.
- **A media assertion fails with "material indisponível" although the
  `lesson_media` rows exist**: the view renders from
  `ClassroomController::resolveMediaAvailability()`, which calls
  `Storage::disk('public')->exists()` per path. A feature test must
  `Storage::fake('public')` **and** actually `put()` bytes at each
  `media->path` (and at the legacy `pdf_path`/`image_path` when exercising
  the fallback) — a factory row alone no longer renders a viewer. See the
  `Storage::fake` setup in `tests/Feature/LessonMediaTest.php`.
- **A manual completion 422s on a lesson that shows no player**: the guard
  is `youtube_video_id`, not `youtube_url`. If an unparseable URL is
  rejected, someone reverted the accessor check to `empty($lesson->
  youtube_url)`; that would make the lesson uncompletable and freeze
  `progress_percentage` for the whole course.
  `LessonProgressControllerTest` covers both directions.
