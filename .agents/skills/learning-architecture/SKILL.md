---
name: learning-architecture
description: >
  Student Learning & Progress domain (SPEC-07): `lesson_progress` schema,
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
  specs:
    - spec/specs/07-student-learning-experience-and-progress.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Learning Architecture

## Overview

SPEC-07 covers Aluno-facing side of Courses (SPEC-05): watching/reading
Lessons, marking them complete, resulting course-wide progress
recalculation. Never mutates `courses`/`modules`/`lessons` themselves. Only
writes `lesson_progress` and `course_user.progress_percentage`/`status`/
`completed_at`.

## Schema (SPEC-00 §2.1.12)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `lesson_progress` | `user_id`, `lesson_id`, `is_completed`, `completion_source` (enum: `manual_click`\|`video_threshold`\|`quiz_passed`, nullable), `watched_seconds` (nullable), `completed_at` | **Cascade-inherited** via `lesson.module.course.org_id` — no own `org_id`, no `OrgScope` (see `tenancy-architecture`) |
| `course_user` (pivot, owned by SPEC-05) | `progress_percentage`, `status`, `completed_at` | Written by this module listener, not owned by it |

`lesson_progress` has unique `(user_id, lesson_id)` constraint. One row per
student per lesson, upserted via `firstOrNew()`, never inserted twice.

## The Completion-Source Rule Per Lesson Type

No single "mark complete" button. Trigger and `completion_source` value
differ by lesson shape:

| Lesson shape | Trigger | `completion_source` | Endpoint |
| --- | --- | --- | --- |
| Video (`youtube_url` set) | YouTube IFrame API polling reports `watched_seconds` ≥ 90% of `duration_seconds` | `video_threshold` | `POST /lessons/{lesson}/progress` |
| Text/PDF/Image (no `youtube_url`) | Explicit "Marcar como concluída" click | `manual_click` | `POST /lessons/{lesson}/complete` |
| Quiz (`type = quiz`) | `SubmitQuizAttemptAction` (SPEC-08) when `quiz_attempts.is_passed = true` | `quiz_passed` | none — no manual button ever renders for quiz lesson |

Both HTTP endpoints reject wrong shape with 422, never silently accept it:
`complete()` 422s on `type=quiz` or non-empty `youtube_url`;
`updateProgress()` 422s on `type=quiz` or empty `youtube_url`. See
`learning-conventions` for exact controller checks.

## `MarkLessonCompleteAction`: the Single Write Path

`app/Actions/MarkLessonCompleteAction.php` is only place any
`lesson_progress` row gets written. Shared today by
`LessonProgressController` two endpoints and, per its own docblock, meant
for SPEC-08 `SubmitQuizAttemptAction` too. Contract:

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

Recalculation is **synchronous**, same request (SPEC-00 §1.2
`QUEUE_CONNECTION=sync` default). No queued job in this pipeline.
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
  `course_completion_rules` row where `rule_type = 'all_lessons'` (SPEC-00
  §2.1 item 15), listener also flips `course_user.status` to `completed`,
  stamps `completed_at`, dispatches `CourseCompletedByStudent` — event
  SPEC-09 (Certificates) listens for. No rule row for course means no
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
  `cancelled` enrollment, or no enrollment row at all, is 403.

Middleware resolves Course from either `{course}` or `{lesson}` route
parameter (supports both route shapes registered in `routes/web.php`),
always `withoutGlobalScopes()`, same reason as above.

## Related Specs

- `spec/specs/07-student-learning-experience-and-progress.md` — RF13, RF14,
  RF15, RF20/RN08, RF24.
- `spec/specs/05-courses-modules-and-content-management.md` /
  `courses-architecture` — `courses`/`modules`/`lessons` schema and
  `OrgScope`/cascade-inheritance model this feature reads from.
- `tenancy-architecture` — `OrgScope` trait and `withoutGlobalScopes()`
  cascade pattern this module relies on throughout.
- `spec/specs/08-*` (Quizzes) — future `quiz_passed` completion source and
  `SubmitQuizAttemptAction`, which reuses `MarkLessonCompleteAction`.
- `spec/specs/09-*` (Certificates) — listens for `CourseCompletedByStudent`.
