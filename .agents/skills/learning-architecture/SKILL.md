---
name: learning-architecture
description: >
  Explains the Student Learning & Progress domain (SPEC-07): the
  `lesson_progress` schema, the completion-source rules per lesson type
  (video threshold / manual click / quiz), the synchronous
  `LessonMarkedAsCompleted` -> `RecalculateCourseProgress` ->
  `CourseCompletedByStudent` event pipeline, and the
  `EnsureStudentIsEnrolled` multi-org access gate. Use whenever designing
  or reviewing a feature that writes `lesson_progress` or
  `course_user.progress_percentage`, before touching
  `MarkLessonCompleteAction`, or when deciding how a student-facing route
  should be tenant/enrollment-gated.
license: MIT
metadata:
  feature: learning
  role: architecture
  specs:
    - spec/specs/07-student-learning-experience-and-progress.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Learning Architecture

## Overview

SPEC-07 covers the Aluno-facing side of Courses (SPEC-05): watching/reading
Lessons, marking them complete, and the resulting course-wide progress
recalculation. It never mutates `courses`/`modules`/`lessons` themselves —
it only writes `lesson_progress` and `course_user.progress_percentage`/
`status`/`completed_at`.

## Schema (SPEC-00 §2.1.12)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `lesson_progress` | `user_id`, `lesson_id`, `is_completed`, `completion_source` (enum: `manual_click`\|`video_threshold`\|`quiz_passed`, nullable), `watched_seconds` (nullable), `completed_at` | **Cascade-inherited** via `lesson.module.course.org_id` — no own `org_id`, no `OrgScope` (see `tenancy-architecture`) |
| `course_user` (pivot, owned by SPEC-05) | `progress_percentage`, `status`, `completed_at` | Written by this module's listener, not owned by it |

`lesson_progress` has a unique `(user_id, lesson_id)` constraint — one row
per student per lesson, upserted via `firstOrNew()`, never inserted twice.

## The Completion-Source Rule Per Lesson Type

There is no single "mark complete" button — the trigger and
`completion_source` value differ by lesson shape:

| Lesson shape | Trigger | `completion_source` | Endpoint |
| --- | --- | --- | --- |
| Video (`youtube_url` set) | YouTube IFrame API polling reports `watched_seconds` ≥ 90% of `duration_seconds` | `video_threshold` | `POST /lessons/{lesson}/progress` |
| Text/PDF/Image (no `youtube_url`) | Explicit "Marcar como concluída" click | `manual_click` | `POST /lessons/{lesson}/complete` |
| Quiz (`type = quiz`) | `SubmitQuizAttemptAction` (SPEC-08) when `quiz_attempts.is_passed = true` | `quiz_passed` | none — no manual button ever renders for a quiz lesson |

Both HTTP endpoints reject the wrong shape with a 422 rather than silently
accepting it: `complete()` 422s on `type=quiz` or a non-empty
`youtube_url`; `updateProgress()` 422s on `type=quiz` or an empty
`youtube_url`. See `learning-conventions` for the exact controller checks.

## `MarkLessonCompleteAction`: the Single Write Path

`app/Actions/MarkLessonCompleteAction.php` is the only place any
`lesson_progress` row is written, shared today by
`LessonProgressController`'s two endpoints and, per its own docblock,
intended for SPEC-08's `SubmitQuizAttemptAction` too. Its contract:

- **Idempotent**: once `is_completed = true`, calling it again never
  flips it back to `false`, never re-sets `completed_at`, and never
  re-dispatches `LessonMarkedAsCompleted`.
- **`watched_seconds` never decreases**: persisted as
  `GREATEST(current, reported)` — passing `null` (manual completions)
  leaves the stored value untouched.
- **Event dispatch is transition-gated**: `LessonMarkedAsCompleted` fires
  only on the `false` -> `true` transition, never on a repeat call — this
  is what keeps `RecalculateCourseProgress` from redundantly recomputing
  on every idle video-progress poll below threshold.

## The Progress Pipeline: `LessonMarkedAsCompleted` -> `RecalculateCourseProgress` -> `CourseCompletedByStudent`

Recalculation is **synchronous**, in the same request (SPEC-00 §1.2's
`QUEUE_CONNECTION=sync` default) — there is no queued job in this
pipeline. `RecalculateCourseProgress::handle()` is auto-discovered purely
from its `handle(LessonMarkedAsCompleted $event)` type-hint (no explicit
`EventServiceProvider` registration to keep in sync).

Formula (equal weight per lesson, ignoring module/workload-hours):

```
progress_percentage = ROUND(completed_published_lessons / total_published_lessons * 100)
```

- Denominator: `Course::publishedLessonsCountFor()` — only
  `lessons.is_published = true`, and since `Module`/`Lesson` carry
  `SoftDeletes`, a soft-deleted module/lesson is excluded automatically
  by its own global scope (no explicit `whereNull('deleted_at')` needed).
  A course with zero published lessons yields `0`, not a division error
  (`$totalPublishedLessons > 0` guard).
- Numerator: `Course::completedLessonsCountFor(User $user)` — same
  published/non-deleted filter, joined through `lesson_progress.is_completed`.
- When the resulting percentage reaches the `required_percentage` of the
  Course's `course_completion_rules` row where `rule_type = 'all_lessons'`
  (SPEC-00 §2.1 item 15), the listener also flips `course_user.status` to
  `completed`, stamps `completed_at`, and dispatches
  `CourseCompletedByStudent` — the event SPEC-09 (Certificates) listens
  for. No rule row for the course means no auto-completion, no matter how
  high the percentage climbs.

Both the Course resolution inside the listener
(`$event->lesson->module->course()->withoutGlobalScopes()->firstOrFail()`)
and every controller/middleware Course lookup in this module deliberately
bypass `OrgScope` — see "Multi-Org Student Access" below for why.

## Multi-Org Student Access & `EnsureStudentIsEnrolled`

An Aluno is not scoped to one Organization: "Meus Cursos" lists enrollments
across every org the student holds an `active`/`completed` `course_user`
row in (`User::courses()`), and the classroom resolves the org from the
Course being viewed, not from the logged-in user's own `org_id` (an Aluno
has none). This is why every Course lookup in this module's
controllers/middleware/listener uses `Course::query()->withoutGlobalScopes()`
or `->course()->withoutGlobalScopes()->firstOrFail()` rather than a typed
`Course $course` implicit route binding or a scoped relation call — the
latter would silently return nothing for an org-less Aluno, turning an
intended "show the course" into a false 404/`null`.

`EnsureStudentIsEnrolled` (registered as the `student.enrolled` route
middleware alias in `bootstrap/app.php`) gates every classroom/lesson/
progress route:

- **Admin**: always allowed — this guard is about enrollment, not tenant
  management, so no active-impersonation requirement applies here.
- **Gestor**: allowed only when their own `org_id` matches the resolved
  Course's `org_id`.
- **Aluno**: allowed only with a `course_user` row in `active` **or**
  `completed` status (`User::hasActiveOrCompletedEnrollment()`) — a
  `cancelled` enrollment, or no enrollment row at all, is a 403.

The middleware resolves the Course from either a `{course}` or `{lesson}`
route parameter (supporting both route shapes registered in
`routes/web.php`), always `withoutGlobalScopes()`, for the same reason as
above.

## Related Specs

- `spec/specs/07-student-learning-experience-and-progress.md` — RF13,
  RF14, RF15, RF20/RN08, RF24.
- `spec/specs/05-courses-modules-and-content-management.md` /
  `courses-architecture` — the `courses`/`modules`/`lessons` schema and
  `OrgScope`/cascade-inheritance model this feature reads from.
- `tenancy-architecture` — the `OrgScope` trait and
  `withoutGlobalScopes()` cascade pattern this module relies on throughout.
- `spec/specs/08-*` (Quizzes) — the future `quiz_passed` completion source
  and `SubmitQuizAttemptAction`, which reuses `MarkLessonCompleteAction`.
- `spec/specs/09-*` (Certificates) — listens for `CourseCompletedByStudent`.
