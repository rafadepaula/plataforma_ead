---
name: learning-maintenance
description: >
  Debugging, testing, and edge-case guide for the Student Learning &
  Progress feature (SPEC-07): the mandatory PHPUnit/Dusk test files,
  common `progress_percentage`/`EnsureStudentIsEnrolled` failure modes,
  and the frontend-build gotcha for `LessonPlayer.js`. Use when
  `MultiOrgStudentClassroomTest`, `EnsureStudentIsEnrolledTest`,
  `CourseProgressCalculationTest`, or `VideoThresholdCompletionTest` is
  failing; progress isn't recalculating; or the video threshold isn't
  auto-completing a lesson.
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

These tests guard the SPEC-07 contract and must stay green (PHPUnit, no
Pest):

- `tests/Unit/Actions/MarkLessonCompleteActionTest.php` — idempotent
  completion, `GREATEST(watched_seconds)`, `completed_at` set only on
  first completion, `LessonMarkedAsCompleted` dispatched only on the
  `false` -> `true` transition.
- `tests/Unit/Listeners/RecalculateCourseProgressTest.php` —
  percentage math (including the zero-published-lessons guard),
  `course_completion_rules`-driven auto-completion, and
  `CourseCompletedByStudent` dispatch.
- `tests/Feature/EnsureStudentIsEnrolledTest.php` — Admin always allowed,
  Gestor gated on `org_id` match, Aluno gated on
  `active`/`completed` `course_user` status (403 for `cancelled` or no
  row).
- `tests/Feature/CourseProgressCalculationTest.php` — end-to-end through
  the real event/listener pipeline (`QUEUE_CONNECTION=sync`).
- `tests/Feature/MultiOrgStudentClassroomTest.php` — an Aluno's "Meus
  Cursos" lists enrollments across multiple Organizations; classroom
  access resolves the org from the Course, not the (org-less) Aluno.
- `tests/Feature/LessonManualCompletionTest.php` — `POST
  /lessons/{lesson}/complete` 422s for quiz/video lessons, succeeds for
  text/PDF/image.
- `tests/Feature/VideoThresholdCompletionTest.php` (Feature) — `POST
  /lessons/{lesson}/progress` persists sub-threshold `watched_seconds`
  without completing, auto-completes at/above 90%.
- `tests/Browser/MultiOrgStudentClassroomTest.php`,
  `tests/Browser/VideoThresholdCompletionTest.php` (Dusk E2E) — full
  browser flow, the latter driving `window.LessonPlayer.reportProgress()`
  directly rather than a real YouTube embed.

Run the narrowest of these first after touching this module:

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
   `RecalculateCourseProgress` never runs inline within the test/request.
2. Is `MarkLessonCompleteAction::execute()` actually completing the
   transition (`false` -> `true`)? A repeat call on an already-completed
   lesson never redispatches `LessonMarkedAsCompleted`, so recalculation
   correctly does not re-run — this is expected, not a bug.
3. Is the lesson `is_published = true`? Unpublished lessons are excluded
   from both `publishedLessonsCountFor()`'s denominator and
   `completedLessonsCountFor()`'s numerator — completing an unpublished
   lesson still writes `lesson_progress` but cannot move the percentage.
4. Is there a `course_completion_rules` row with `rule_type =
   'all_lessons'` for the course? Without one, the course will never
   auto-transition to `completed` no matter how high the percentage
   climbs — this is correct per SPEC-00 §2.1 item 15, not a bug in the
   listener.

## `EnsureStudentIsEnrolled` Returning the Wrong Status

- A 403 for a Gestor: confirm `$user->org_id` matches the resolved
  Course's `org_id` — the middleware compares ints (`(int)` cast on both
  sides), so a string/int mismatch from a stale cast elsewhere is not the
  cause; check the Course was actually resolved `withoutGlobalScopes()`
  and not silently 404'd first.
- A 403 for an Aluno with what looks like a valid enrollment: check the
  `course_user.status` value directly — only `active` and `completed`
  pass `User::hasActiveOrCompletedEnrollment()`; a `cancelled` row (e.g.
  after `EnrollmentController::destroy()`) is a deliberate 403, not a bug.
- A 404 instead of the expected 403: the middleware's `resolveCourse()`
  uses `findOrFail()`/`firstOrFail()` on a `withoutGlobalScopes()` query —
  if that still 404s, the Course/Lesson genuinely doesn't exist (soft-
  deleted or bad ID), which is correct; a 404 here is never this
  middleware silently mis-scoping.

## Frontend Build Staleness

If `window.LessonPlayer` is `undefined` in the browser (video
polling/manual completion silently does nothing, Dusk tests time out
waiting on player state), `public/build` is stale relative to
`resources/js/modules/LessonPlayer.js`/`resources/js/app.js` — run
`vendor/bin/sail npm run build` (or ask the user to run `npm run dev`/
`composer run dev`) before re-running Dusk.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to `MarkLessonCompleteAction`, `RecalculateCourseProgress`,
`EnsureStudentIsEnrolled`, the `LessonMarkedAsCompleted`/
`CourseCompletedByStudent` events, `StudentCourseController`/
`ClassroomController`/`LessonProgressController`, the
`student.enrolled`/`role:aluno` routes, `lesson_progress`'s schema, or
`LessonPlayer.js` **must** update all three learning skills
(`learning-architecture`, `learning-conventions`, `learning-maintenance`)
in the same change, before the task is considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if the change affects what a
  reviewer must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fails the
  build if any of the three `learning-*` skills is missing.

## Related Specs

- `spec/specs/07-student-learning-experience-and-progress.md` — RF13,
  RF14, RF15, RF20/RN08, RF24.
- `courses-maintenance` — the analogous module this one reads Course/
  Module/Lesson data from; mirrors its `withoutGlobalScopes()` cross-
  tenant-lookup convention.
- `tenancy-maintenance` — the underlying `OrgScope` contract this module
  deliberately bypasses throughout.
