---
name: courses-maintenance
description: >
  Courses/Modules/Lessons domain: schema of `courses`/`modules`/`lessons`/`course_user`,
  OrgScope-on-Course vs cascade-inherited Module/Lesson tenancy, Course delete guard,
  publication-state visibility, FileUploadService/VideoUrlSanitizerManager usage,
  Policy conventions, AJAX reorder endpoint shape, mandatory tests. Use when designing
  or reviewing features touching Course/Module/Lesson data, before adding a new
  column/relation to any of the three tables, when writing controller/Policy/Form
  Request/Service managing those records or handling Lesson media upload or YouTube
  URL, or when `MultiTenantCourseManagementTest`, `ModuleReorderTest` or
  `LessonMultimediaTest` fails, drag-and-drop reorder does not persist, or a Lesson
  file upload lands in the wrong tenant folder.
license: MIT
metadata:
  feature: courses
  roles: [architecture, conventions, maintenance]
---

# Courses, Modules & Lessons (`courses-maintenance`)

Courses/Modules/Lessons domain: `courses`/`modules`/`lessons`/`course_user` schema, `OrgScope`-on-Course vs cascade-inherited Module/Lesson tenancy, Course delete guard, publication-state visibility, media upload and AJAX reorder.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching Course/Module/Lesson data, before adding a new column/relation to any of the three tables, or when deciding how a Gestor-facing action gets tenant-scoped. |
| `resource/conventions.md` | Writing controller, Policy, Form Request, or Service managing `Course`/`Module`/`Lesson` records, handling Lesson media upload or YouTube URL, or wiring reorder endpoints. |
| `resource/maintenance.md` | `MultiTenantCourseManagementTest`, `ModuleReorderTest`, or `LessonMultimediaTest` fails; drag-and-drop reorder not persisting; Lesson file upload lands in wrong tenant folder; YouTube embed preview shows for a URL that should be rejected. |
