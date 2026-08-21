---
name: learning-maintenance
description: >
  Debug, test, edge-case guide for Student Learning & Progress (SPEC-07):
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
  specs:
    - spec/specs/07-student-learning-experience-and-progress.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Learning Maintenance

## Mandatory Test Coverage for This Module

Tests guard SPEC-07 contract. Must stay green (PHPUnit, no Pest):

- `tests/Unit/Actions/MarkLessonCompleteActionTest.php` — idempotent
  completion, `GREATEST(watched_seconds)`, `completed_at` set only on
  first completion, `LessonMarkedAsCompleted` dispatched only on `false`
  -> `true` transition.
- `tests/Unit/Listeners/RecalculateCourseProgressTest.php` — percentage
  math (including zero-published-lessons guard),
  `course_completion_rules`-driven auto-completion,
  `CourseCompletedByStudent` dispatch.
- `tests/Feature/EnsureStudentIsEnrolledTest.php` — Admin always allowed,
  Gestor gated on `org_id` match, Aluno gated on `active`/`completed`
  `course_user` status (403 for `cancelled` or no row).
- `tests/Feature/CourseProgressCalculationTest.php` — end-to-end through
  real event/listener pipeline (`QUEUE_CONNECTION=sync`).
- `tests/Feature/MultiOrgStudentClassroomTest.php` — Aluno "Meus Cursos"
  list enrollments across multiple Organizations; classroom access
  resolve org from Course, not from (org-less) Aluno.
- `tests/Feature/LessonManualCompletionTest.php` — `POST
  /lessons/{lesson}/complete` 422 for quiz/video lessons, succeed for
  text/PDF/image.
- `tests/Feature/VideoThresholdCompletionTest.php` (Feature) — `POST
  /lessons/{lesson}/progress` persist sub-threshold `watched_seconds`
  without completing, auto-complete at/above 90%.
- `tests/Browser/MultiOrgStudentClassroomTest.php`,
  `tests/Browser/VideoThresholdCompletionTest.php` (Dusk E2E) — full
  browser flow, latter drive `window.LessonPlayer.reportProgress()`
  directly rather than real YouTube embed.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=MarkLessonCompleteActionTest
vendor/bin/sail artisan test --filter=RecalculateCourseProgressTest
vendor/bin/sail artisan test --filter=EnsureStudentIsEnrolledTest
vendor/bin/sail artisan test --filter=CourseProgressCalculationTest
vendor/bin/sail artisan test --filter=MultiOrgStudentClassroomTest
vendor/bin/sail artisan test --filter=LessonManualCompletionTest
vendor/bin/sail artisan test --filter=VideoThresholdCompletionTest
vendor/bin/sail artisan dusk --filter=MultiOrgStudentClassroomTest
vendor/bin/sail artisan dusk --filter=VideoThresholdCompletionTest
```

## `progress_percentage` Not Updating

Check, in order:

1. `QUEUE_CONNECTION` — must resolve to `sync` (SPEC-00 §1.2 default) or
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
   to `completed` no matter how high percentage climb — correct per
   SPEC-00 §2.1 item 15, not bug in listener.

## `EnsureStudentIsEnrolled` Returning the Wrong Status

- 403 for Gestor: confirm `$user->org_id` match resolved Course
  `org_id` — middleware compare ints (`(int)` cast on both sides), so
  string/int mismatch from stale cast elsewhere is not cause; check
  Course actually resolved `withoutGlobalScopes()` and not silently 404'd
  first.
- 403 for Aluno with what look like valid enrollment: check
  `course_user.status` value directly — only `active` and `completed`
  pass `User::hasActiveOrCompletedEnrollment()`; `cancelled` row (e.g.
  after `EnrollmentController::destroy()`) is deliberate 403, not bug.
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

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to `MarkLessonCompleteAction`, `RecalculateCourseProgress`,
`EnsureStudentIsEnrolled`, `LessonMarkedAsCompleted`/
`CourseCompletedByStudent` events, `StudentCourseController`/
`ClassroomController`/`LessonProgressController`,
`student.enrolled`/`role:aluno` routes, `lesson_progress` schema, or
`LessonPlayer.js` **must** update all three learning skills
(`learning-architecture`, `learning-conventions`, `learning-maintenance`)
in same change, before task considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affect what reviewer must
  check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fail build if
  any of three `learning-*` skills missing.

## Related Specs

- `spec/specs/07-student-learning-experience-and-progress.md` — RF13,
  RF14, RF15, RF20/RN08, RF24.
- `courses-maintenance` — analogous module this one read Course/Module/
  Lesson data from; mirror its `withoutGlobalScopes()` cross-tenant-lookup
  convention.
- `tenancy-maintenance` — underlying `OrgScope` contract this module
  deliberately bypass throughout.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module, spec, or use case. Consequences when
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
