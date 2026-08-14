---
name: dashboard-architecture
description: >
  Admin Dashboard, Analytics & System Settings domain (SPEC-12):
  `admin.dashboard` route contract feeding mockup's 4 stat cards plus
  recent-enrollments table, how `DashboardMetricsService` replicate `OrgScope`
  admin-global-vs-gestor-own-org branching for models (`Certificate`,
  `course_user`, `User`) that cascade-inherited instead of directly org-scoped,
  streamed CSV export O(1)-RAM contract, `system_settings` org-then-global
  override resolution reused from `SettingService`. Use when design or review
  feature touching dashboard KPIs, CSV export pipeline, or org-level
  SMTP/logo/signature settings, or before add new stat/report type.
license: MIT
metadata:
  feature: dashboard
  role: architecture
  specs:
    - spec/specs/12-admin-dashboard-analytics-and-system-settings.md
    - spec/docs/mockups/07-dashboard-admin.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Dashboard Architecture

## Overview

SPEC-12 have 3 pieces sharing one screen and one settings screen:

1. **Admin/Gestor Dashboard** (`GET /admin/dashboard`, route name **must** be
   exactly `admin.dashboard` — `components/layout/sidebar.blade.php` already
   reference it defensively via `Route::has('admin.dashboard')` and silently
   degrade to `#` if name ever drift). Render 4 stat cards (`active_students`,
   `certificates_issued`, `completion_rate`, `courses_count`) and "Matrículas
   recentes" table, per `spec/docs/mockups/07-dashboard-admin.md` §3/§4 exact
   Blade/data contract.
2. **Streamed CSV export** (`GET /admin/reports/{type}/export`, route name
   `reports.export`). Must stream via `response()->streamDownload()` plus
   `chunk()`/`lazy()`, never buffer full dataset into memory first (SPEC-00 §1.2
   128M shared-hosting constraint).
3. **`system_settings` org-override editing** (`GET`/`PUT /admin/settings`, route
   names `settings.edit`/`settings.update`) for SMTP/logo/signature, built on top
   of pre-existing `system_settings` table/model (do not recreate — see Schema
   below) via `SettingService`.

Both Dashboard and CSV export gated `role:admin|gestor` with no dedicated Policy
class (mirror `quiz-attempts.pending`/`forum-moderation.index` precedent of
role-middleware-only for cross-cutting, non-resource screens — see
`quizzes-conventions`/`forum-conventions`).

## Schema (SPEC-00 §2.1.20 — already exists, do not recreate)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `system_settings` | composite PK `(org_id, key)`, `org_id` uses a `GLOBAL_ORG_ID` **sentinel value (not `NULL`)** for the global row, `value` (text/JSON) | **Not** `OrgScope`-scoped — model deliberately opts out (composite-PK sentinel design incompatible with global scope's implicit `WHERE org_id = ...`) |

`SystemSetting::forKey()/forOrg()` query helpers already exist from prior spec
pass. `SettingService` thin `get()`/`set()`/`forget()` wrapper around them with
`Cache::remember()`, mirroring `HelpArticleResolverService`
org-specific-then-global fallback pattern (see `help-architecture`) but keyed by
`GLOBAL_ORG_ID` sentinel instead of nullable `org_id`.

## Why Metrics Service Cannot Just Rely on `OrgScope`

`Course` and `InvitationLink` carry `OrgScope` and resolve
admin-global-vs-gestor-own-org automatically from
`session('active_org_id')`/`$user->org_id` (see `tenancy-architecture`).
`Certificate`, `course_user` pivot, and `User` do **not** carry `OrgScope`.
`Certificate`/`course_user` cascade-inherited through `courses.org_id` (mirror
`certificates-architecture` note on `Certificate` never carrying `OrgScope`
directly), and `User.org_id` plain nullable column with no scope at all.
`DashboardMetricsService` must therefore:

- Resolve `Course::query()` first (which **does** get `OrgScope` filtering "for
  free" for Gestor, or Admin impersonating Org), then join
  `course_user`/`Certificate`/`User` through `courses.org_id`/`course_id` rather
  than query those tables directly and hope global scope narrow them. It will
  not.
- For Admin with **no** active "Impersonate Org" session, replicate `OrgScope`'s
  own "admin + no `active_org_id` in session => no `WHERE` clause added" branch
  **manually** for every raw `Certificate`/`course_user`/`User` query, since none
  of those 3 models inherit that behavior from scope.

## CSV Streaming Contract

CSV builder (`CsvStreamExportService`/`StreamOrgReportCsvAction`) wrap
`response()->streamDownload()` and write with `fputcsv()` inside
`Model::query()->chunk(500, ...)` or `->lazy()` loop. Only approach keeping peak
memory O(1) regardless of dataset size (SPEC-00 §1.2). Parameterized by same
org-filter branching described above (mirror `DashboardMetricsService` scoping,
not second ad-hoc implementation) and by report `type` (`enrollments`,
`certificates`, ...). Gestor `org_id` always resolved from `$user->org_id`
server-side, never trusted from request input. Gestor passing
`?org_id=<anotherOrg>` must 403, not silently scope to own org (see
`dashboard-conventions` for exact guard).

## Relationship to Other Modules

- Reuse `HelpArticleResolverService` org-then-global fallback shape (see
  `help-architecture`) as template for `SettingService`, but against sentinel-PK
  `system_settings` schema rather than nullable-`org_id` table.
- Reuse `FileUploadService` (see `courses-conventions`) for settings screen logo
  upload, same convention as `Organization` `logo_path` field.
- Dashboard `recentEnrollments` shape (`student_name`, `course_name`,
  `status_label`, `status_badge_variant`) presentation data computed by service,
  not raw `course_user` row. Do not leak pivot column names (`status`,
  `progress_percentage`) into view; translate them in `DashboardMetricsService`.
