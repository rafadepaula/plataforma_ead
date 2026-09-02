---
name: courses-architecture
description: >
  Courses/Modules/Lessons domain: courses/modules/lessons/course_user
  schema, OrgScope-on-Course vs cascade-inherited Module/Lesson tenancy, Course
  delete guard, publication-state visibility rules. Use when design or review
  feature touching Course/Module/Lesson data, before add new column/relation to
  any of three tables, or when decide how Gestor-facing action get tenant-scoped.
license: MIT
metadata:
  feature: courses
  role: architecture
---

# Courses Architecture

## Overview

Gestor (`role:gestor`) get CRUD over own Organization's Courses, and
each Course's Modules and Lessons. Admin (`role:admin`) same abilities, scoped
to Organization it impersonate now (see `tenancy-architecture` Impersonate Org
section). Admin with no active impersonation manage nothing here. No fallback to
"manage everything globally" mode for this domain.

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `courses` | `org_id`, `title`, `description`, `workload_hours`, `is_published` | **Directly org-scoped** — `OrgScope` trait |
| `course_user` (pivot) | `user_id`, `course_id`, `status` (`active`\|`cancelled`\|`completed`), `progress_percentage`, `enrolled_at`, `completed_at`, `expires_at` nullable | Not org-scoped — enrollment cross orgs (see `tenancy-architecture`). `expires_at` backs `Course::enrollmentDisplayStatusFor()`'s derived `expirado` chip on an `active` row past deadline — not a 4th pivot `status` value |
| `modules` | `course_id`, `title`, `description`, `order_index` | **Cascade-inherited** via `courses.org_id` — no own `org_id`, no `OrgScope` |
| `lessons` | `module_id`, `title`, `type` (`content`\|`quiz`), `content_text`, `video_provider` (`youtube`\|`vimeo`, nullable, default `youtube`), `video_url`, `pdf_path`, `image_path`, `order_index`, `is_published` | **Cascade-inherited** via `modules`, `courses.org_id` |
| `lesson_media` | `lesson_id` FK `cascadeOnDelete`, `kind` ENUM(`image`\|`pdf`), `path`, `original_name` nullable, `size_bytes` nullable unsignedBigInteger; INDEX(`lesson_id`,`kind`) | **Cascade-inherited** via `lesson -> module -> courses.org_id` — no own `org_id`, no `OrgScope` (same as `Module`/`Lesson`) |

All three (`courses`, `modules`, `lessons`) use `SoftDeletes`. Every delete
action here soft-delete (`deleted_at`), never hard `DELETE`. Matter most for
`lessons`: `lesson_progress` must survive Lesson/Module/Course
archived, so no controller/service here may `forceDelete()` or cascade-purge
`lesson_progress`. See "Soft-Delete, Never Purge" below.

## OrgScope on Course vs Cascade-Inherited Module/Lesson

`Course` use `OrgScope` trait directly (see `tenancy-architecture`): every query
filtered to acting Gestor's `org_id` (or Admin's impersonated org), and
`Course::create()` auto-assign `org_id` from resolved tenant context, throw
`UnresolvedOrgContextException` if none resolve (example: Admin creating Course
with no active Impersonate Org).

`Module` and `Lesson` **cascade-inherited**: no `org_id` column, must NOT get
`OrgScope` trait (see each model docblock). Tenant boundary implied by
`modules.course_id`, `courses.org_id` (and one level deeper for
`lessons.module_id`, `modules.course_id`, `courses.org_id`). One important
consequence:

**Route-model-bound `{course}` OrgScope-protected for free** (Gestor hitting
`/courses/{id}` for other org's course get 404 automatically, since
`Course::find()` never see row). **Route-model-bound `{module}`/`{lesson}` NOT
protected same way** — Gestor guessing other org's module/lesson ID by URL *will*
find row (no scope filter it out), so `ModulePolicy`/`LessonPolicy` only
enforcement point for that case. See `courses-conventions` for exact pattern
(`withoutGlobalScopes()` when read parent Course inside Policy — read through
normal scoped relation while acting as different-org Gestor return `null`
instead of real cross-tenant row, turn intended 403 into type error).

## Lesson Media: `lesson_media` Is the Read Model, Legacy Columns Are Compat

Lessons support MULTIPLE images and MULTIPLE PDFs per lesson, so
attachments live in `lesson_media` rows (`LessonMedia::KIND_IMAGE`/`KIND_PDF`),
one row per file, written by `LessonController::syncMedia()` via
`FileUploadService::storeImages()`/`storePdfs()` (the media-only inputs are
first stripped out of the mass-assigned attributes by
`LessonController::validatedAttributes()`). Rules of the road:

- Every read path iterates `Lesson::media()` (or the `images()`/`pdfs()`
  scopes). The legacy `lessons.image_path`/`pdf_path` VARCHAR columns are kept
  and backfilled from (migration `2026_08_23_000002`), synced with the first
  attachment of each kind, and nulled when that kind's last attachment is
  removed — they exist purely so legacy/classroom read paths keep working.
  Never write a new consumer against them.
- Per-file limits: images 2MB (`max:2048`), PDFs 10MB (`max:10240`), enforced
  per element (`images.*`/`pdfs.*`) so a request mixing one oversized file
  with valid siblings fails atomically — no partial upload, no Lesson row.
- Removal is a server round-trip: the update request carries `removed_media[]`
  of `lesson_media` ids, validated only as `['integer']` (no `exists` rule —
  an id for another lesson/tenant is not rejected at validation time).
  `syncMedia()` scopes the delete query to the route-bound lesson's own
  `media()` relation, so a foreign id is silently ignored rather than
  triggering a 422, before deleting the row AND
  `Storage::disk('public')->delete()`ing the file.
- Deletion semantics: `lesson_media.lesson_id` is `cascadeOnDelete` at the DB
  level, but Lesson deletion is a SOFT delete, so media rows are only purged
  on a hard delete. The Gestor ConfirmModal cascade warning ("As {N} lições
  deste módulo também serão removidas...") mirrors the DB cascade, while the
  actual soft delete leaves lesson rows and `lesson_progress` intact.

## Course Delete Guard

Course may not soft-delete while it have at least one `course_user` row with
`status = 'active'`. `Course::hasActiveEnrollments()` / inverse
`Course::canBeDeleted()` check exactly that (`cancelled`/`completed` enrollments
never block deletion). Enforced in **two places deliberately**, not duplicated by
accident:

1. `CoursePolicy::delete()` return `false` when `hasActiveEnrollments()` true, so
   `Gate::authorize('delete', $course)` / `@can('delete', $course)` already
   short-circuit with plain 403 in common case.
2. `CourseController::destroy()` independently check
   `$course->hasActiveEnrollments()` and throw
   `CourseHasActiveEnrollmentsException` (mapped to HTTP 422 with Portuguese
   message in `bootstrap/app.php`). This defense-in-depth path is the one the
   mapped 422 response refers to.

Both checks read same `Course::hasActiveEnrollments()` method. Never duplicate
`wherePivot('status', 'active')->exists()` query elsewhere.

## Soft-Delete, Never Purge

Deleting Course, Module, or Lesson only set `deleted_at`. Do not add
`forceDelete()`/cascade-purge path anywhere in this feature's
controllers/services: `lesson_progress` reference `lesson_id` and must
stay queryable (example: student historical completion record) even after
Lesson's parent chain archived.

## Publication-State Visibility

`courses.is_published` / `lessons.is_published` gate what enrolled **Aluno** see
on student-facing side (the learning domain own that read path entirely). This
feature only expose toggle in Gestor-facing CRUD form. It not filter any query
by `is_published` itself, since every read here already authenticated
Gestor/Admin managing own content, not student consuming it.

## `lessons.type = 'quiz'` Is Placeholder Here

The quizzes domain own quiz question authoring. This feature's Lesson form only populate
`type = content` rows (Rich Text / Imagem / PDF / Vídeo YouTube/Vimeo — the four content
kinds).
`quiz` exist as schema value and disabled/placeholder option in UI, never fully
wired content path in this feature. Do not add
`content_text`/`video_provider`/`video_url`/`pdf_path`/`image_path` population logic for
`type = quiz` rows.

## Gestor Course Catalog Read Model

`CourseController::index(Request)` builds tenant-scoped management catalog.
Input normalization happens before query: `search` accepts scalar string only;
`status` whitelist = `all|published|draft`; array/unknown input becomes neutral
filter, never exception. Query applies partial title search, publication filter,
alphabetical order, 10-row `LengthAwarePaginator`, `withQueryString()`.

Single query supplies four aggregates:

- `modules_count`
- `lessons_count`
- `students_count` = every enrollment status, shown in Alunos column
- `active_students_count` = pivot `course_user.status = active`, drives delete UI

Never use `students_count` for delete protection. Cancelled/completed rows belong
in displayed total but cannot disable removal.

## Related

- `tenancy-architecture` — `OrgScope`, `RolesEnum`, Impersonate Org.
- `learning-architecture` (student-facing course consumption,
  `lesson_progress`, publication visibility) and `quizzes-architecture`
  (quiz authoring) — both build on top of this feature's schema without
  modifying it.
- The "Meus Cursos" student catalog (see `learning-architecture`) — added
  `course_user.expires_at` (documented above) and several student-facing
  `Course` read helpers (`firstPublishedLessonFor()`/`resumeLessonFor()`/
  `enrollmentDisplayStatusFor()`), which serve that module's read path,
  not this one's Gestor CRUD.
