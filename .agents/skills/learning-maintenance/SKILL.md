---
name: learning-maintenance
description: >
  Student Learning & Progress: `lesson_progress` schema, completion-source rules per
  lesson type (video threshold / manual click / quiz), synchronous
  `LessonMarkedAsCompleted` -> `RecalculateCourseProgress` -> `CourseCompletedByStudent`
  pipeline, `EnsureStudentIsEnrolled` gate, `MarkLessonCompleteAction`, 422-shape
  guard, `LessonPlayer.js` conventions. Use when designing or reviewing features
  writing `lesson_progress` or `course_user.progress_percentage`, before touching
  `MarkLessonCompleteAction`, when gating a student-facing route or rendering
  lesson-completion UI, or when `MultiOrgStudentClassroomTest`,
  `EnsureStudentIsEnrolledTest`, `CourseProgressCalculationTest` or
  `VideoThresholdCompletionTest` fails, progress does not recalculate, or the video
  threshold does not auto-complete the lesson.
license: MIT
metadata:
  feature: learning
  roles: [architecture, conventions, maintenance]
---

# Student Learning & Progress (`learning-maintenance`)

Student Learning & Progress: `lesson_progress` schema, completion-source rules per lesson type (video threshold / manual click / quiz), the synchronous `LessonMarkedAsCompleted` → `RecalculateCourseProgress` → `CourseCompletedByStudent` pipeline, `EnsureStudentIsEnrolled` multi-org access gate.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features writing `lesson_progress` or `course_user.progress_percentage`, before touching `MarkLessonCompleteAction`, or when deciding how a student-facing route gets tenant/enrollment-gated. |
| `resource/conventions.md` | Writing controller, action, or JS module touching `lesson_progress`, gating a student-facing route, or rendering/updating lesson-completion UI. |
| `resource/maintenance.md` | `MultiOrgStudentClassroomTest`, `EnsureStudentIsEnrolledTest`, `CourseProgressCalculationTest`, or `VideoThresholdCompletionTest` fails; progress not recalculating; video threshold not auto-completing lesson. |
