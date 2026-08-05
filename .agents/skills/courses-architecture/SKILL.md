---
name: courses-architecture
description: >
  Explains the Courses/Modules/Lessons domain (SPEC-05): the
  courses/modules/lessons/course_user schema, OrgScope-on-Course vs.
  cascade-inherited Module/Lesson tenancy, the Course delete guard, and
  publication-state visibility rules. Use whenever designing or reviewing a
  feature that touches Course/Module/Lesson data, before adding a new
  column/relation to any of the three tables, or when deciding how a Gestor
  -facing action should be tenant-scoped.
license: MIT
metadata:
  feature: courses
  role: architecture
  specs:
    - spec/specs/05-courses-modules-and-content-management.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Courses Architecture

## Overview

RF06/RF07 give a Gestor (`role:gestor`) CRUD over their own Organization's
Courses, and each Course's Modules and Lessons. Admin (`role:admin`) has the
same abilities, scoped to whichever Organization it is currently
impersonating (see `tenancy-architecture`'s Impersonate Org section) — an
Admin with no active impersonation manages nothing here, it does not fall
back to a "manage everything globally" mode for this domain.

## Schema (SPEC-00 §2.1.4–2.1.6)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `courses` | `org_id`, `title`, `description`, `workload_hours`, `is_published` | **Directly org-scoped** — `OrgScope` trait |
| `course_user` (pivot) | `user_id`, `course_id`, `status` (`active`\|`cancelled`\|`completed`), `progress_percentage`, `enrolled_at`, `completed_at` | Not org-scoped — enrollment crosses orgs (see `tenancy-architecture`) |
| `modules` | `course_id`, `title`, `description`, `order_index` | **Cascade-inherited** via `courses.org_id` — no own `org_id`, no `OrgScope` |
| `lessons` | `module_id`, `title`, `type` (`content`\|`quiz`), `content_text`, `youtube_url`, `pdf_path`, `image_path`, `order_index`, `is_published` | **Cascade-inherited** via `modules` → `courses.org_id` |

All three (`courses`, `modules`, `lessons`) use `SoftDeletes` — every delete
action in this feature is a soft-delete (`deleted_at`), never a hard
`DELETE`. This matters most for `lessons`: `lesson_progress` (SPEC-07) must
survive a Lesson/Module/Course being archived, so no controller/service in
this feature may `forceDelete()` or cascade-purge `lesson_progress` — see
"Soft-Delete, Never Purge" below.

## OrgScope on Course vs. Cascade-Inherited Module/Lesson

`Course` uses the `OrgScope` trait directly (see `tenancy-architecture`):
every query is transparently filtered to the acting Gestor's `org_id` (or the
Admin's impersonated org), and `Course::create()` auto-assigns `org_id` from
the resolved tenant context, throwing `UnresolvedOrgContextException` if none
resolves (e.g. an Admin creating a Course with no active Impersonate Org).

`Module` and `Lesson` are **cascade-inherited**: they have no `org_id` column
and must NOT get the `OrgScope` trait (see each model's docblock). Their
tenant boundary is implied by `modules.course_id` → `courses.org_id` (and one
level deeper for `lessons.module_id` → `modules.course_id` →
`courses.org_id`). This has one important consequence:

**Route-model-bound `{course}` is OrgScope-protected for free** (a Gestor
hitting `/courses/{id}` for another org's course gets a 404 automatically,
since `Course::find()` never sees the row). **Route-model-bound `{module}`/
`{lesson}` are NOT protected the same way** — a Gestor guessing another org's
module/lesson ID by URL *will* find the row (no scope filters it out), so
`ModulePolicy`/`LessonPolicy` are the only enforcement point for that case.
See `courses-conventions` for the exact pattern (`withoutGlobalScopes()` when
reading the parent Course from inside a Policy — reading it through the
normal scoped relation while acting as a different-org Gestor returns `null`
instead of the real cross-tenant row, turning an intended 403 into a type
error).

## The Course Delete Guard

A Course may not be soft-deleted while it has at least one `course_user` row
with `status = 'active'` — `Course::hasActiveEnrollments()` / the inverse
`Course::canBeDeleted()` check exactly that (`cancelled`/`completed`
enrollments never block deletion). This is enforced in **two places
deliberately**, not duplicated by accident:

1. `CoursePolicy::delete()` returns `false` when `hasActiveEnrollments()` is
   true — so `Gate::authorize('delete', $course)` / `@can('delete',
   $course)` already short-circuit with a plain 403 in the common case.
2. `CourseController::destroy()` independently checks
   `$course->hasActiveEnrollments()` and throws
   `CourseHasActiveEnrollmentsException` (mapped to HTTP 422 with a
   Portuguese message in `bootstrap/app.php`) — this is the defense-in-depth
   path and the one the acceptance criteria's "422" language refers to.

Both checks read the same `Course::hasActiveEnrollments()` method — never
duplicate the `wherePivot('status', 'active')->exists()` query elsewhere.

## Soft-Delete, Never Purge

Deleting a Course, Module, or Lesson only ever sets `deleted_at`. Do not add
a `forceDelete()`/cascade-purge path anywhere in this feature's
controllers/services: `lesson_progress` (SPEC-07) references `lesson_id` and
must remain queryable (e.g. for a student's historical completion record)
even after the Lesson's parent chain is archived.

## Publication-State Visibility (cross-ref SPEC-07)

`courses.is_published` / `lessons.is_published` gate what an enrolled
**Aluno** sees on the student-facing side (SPEC-07 owns that read path
entirely). This feature (SPEC-05) only exposes the toggle in the
Gestor-facing CRUD form — it does not itself filter any query by
`is_published`, since every read here is already an authenticated
Gestor/Admin managing their own content, not a student consuming it.

## `lessons.type = 'quiz'` Is a Placeholder Here

SPEC-08 owns quiz question authoring. This feature's Lesson form only
populates `type = content` rows (Rich Text / Imagem / PDF / YouTube — RF07's
four kinds) — `quiz` exists as a schema value and a disabled/placeholder
option in the UI, never a fully wired content path in this spec's scope. Do
not add `content_text`/`youtube_url`/`pdf_path`/`image_path` population logic
for `type = quiz` rows.

## Related Specs

- `spec/specs/05-courses-modules-and-content-management.md` — this
  feature's full RF06/RF07 requirements.
- `spec/specs/00-architecture-database-and-guardrails.md` §2.1.4–2.1.6 —
  full column/index/`onDelete` definitions.
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, Impersonate Org.
- `spec/specs/07-student-learning-experience-and-progress.md` (student
  -facing course consumption, `lesson_progress`, publication visibility) and
  `spec/specs/08-quizzes-and-evaluations-engine.md` (quiz authoring) — both
  build on top of this feature's schema without modifying it.
