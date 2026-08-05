---
name: dashboard-architecture
description: >
  Explains the Admin Dashboard, Analytics & System Settings domain
  (SPEC-12): the `admin.dashboard` route contract feeding the mockup's 4
  stat cards + recent-enrollments table, how `DashboardMetricsService`
  replicates `OrgScope`'s admin-global-vs-gestor-own-org branching for
  models (`Certificate`, `course_user`, `User`) that are cascade-inherited
  rather than directly org-scoped, the streamed CSV export's O(1)-RAM
  contract, and the `system_settings` org-then-global override
  resolution reused from `SettingService`. Use whenever designing or
  reviewing a feature that touches dashboard KPIs, the CSV export
  pipeline, or org-level SMTP/logo/signature settings, or before adding a
  new stat/report type.
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

SPEC-12 has 3 pieces that share one screen and one settings screen:

1. **The Admin/Gestor Dashboard** (`GET /admin/dashboard`, route name
   **must** be exactly `admin.dashboard` — `components/layout/sidebar.blade.php`
   already references it defensively via `Route::has('admin.dashboard')`
   and silently degrades to `#` if the name ever drifts). Renders 4 stat
   cards (`active_students`, `certificates_issued`, `completion_rate`,
   `courses_count`) and a "Matrículas recentes" table, per
   `spec/docs/mockups/07-dashboard-admin.md` §3/§4's exact Blade/data
   contract.
2. **Streamed CSV export** (`GET /admin/reports/{type}/export`, route
   name `reports.export`) — must stream via `response()->streamDownload()`
   + `chunk()`/`lazy()`, never buffer a full dataset into memory first
   (SPEC-00 §1.2's 128M shared-hosting constraint).
3. **`system_settings` org-override editing** (`GET`/`PUT /admin/settings`,
   route names `settings.edit`/`settings.update`) for SMTP/logo/signature,
   built on top of the pre-existing `system_settings` table/model (do not
   recreate — see Schema below) via `SettingService`.

Both the Dashboard and the CSV export are gated `role:admin|gestor` with
no dedicated Policy class (mirrors the `quiz-attempts.pending`/
`forum-moderation.index` precedent of role-middleware-only for
cross-cutting, non-resource screens — see `quizzes-conventions`/
`forum-conventions`).

## Schema (SPEC-00 §2.1.20 — already exists, do not recreate)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `system_settings` | composite PK `(org_id, key)`, `org_id` uses a `GLOBAL_ORG_ID` **sentinel value (not `NULL`)** for the global row, `value` (text/JSON) | **Not** `OrgScope`-scoped — the model deliberately opts out (composite-PK sentinel design is incompatible with a global scope's implicit `WHERE org_id = ...`) |

`SystemSetting::forKey()/forOrg()` query helpers already exist from a
prior spec pass — `SettingService` is a thin `get()`/`set()`/`forget()`
wrapper around them with `Cache::remember()`, mirroring
`HelpArticleResolverService`'s org-specific-then-global fallback pattern
(see `help-architecture`) but keyed by the `GLOBAL_ORG_ID` sentinel
instead of a nullable `org_id`.

## Why the Metrics Service Cannot Just Rely on `OrgScope`

`Course` and `InvitationLink` carry `OrgScope` and resolve
admin-global-vs-gestor-own-org automatically from
`session('active_org_id')`/`$user->org_id` (see `tenancy-architecture`).
`Certificate`, the `course_user` pivot, and `User` do **not** carry
`OrgScope` — `Certificate`/`course_user` are cascade-inherited through
`courses.org_id` (mirroring `certificates-architecture`'s note on
`Certificate` never carrying `OrgScope` directly), and `User.org_id` is a
plain nullable column with no scope at all. `DashboardMetricsService`
must therefore:

- Resolve `Course::query()` first (which **does** get `OrgScope`'s
  filtering "for free" for a Gestor, or an Admin impersonating an Org),
  then join `course_user`/`Certificate`/`User` through
  `courses.org_id`/`course_id` rather than querying those tables
  directly and hoping a global scope narrows them — it will not.
- For an Admin with **no** active "Impersonate Org" session, replicate
  `OrgScope`'s own "admin + no `active_org_id` in session => no `WHERE`
  clause added" branch **manually** for every raw `Certificate`/
  `course_user`/`User` query, since none of those 3 models inherit that
  behavior from the scope.

## CSV Streaming Contract

The CSV builder (`CsvStreamExportService`/`StreamOrgReportCsvAction`)
wraps `response()->streamDownload()` and writes with `fputcsv()` inside a
`Model::query()->chunk(500, ...)` or `->lazy()` loop — this is the only
approach that keeps peak memory O(1) regardless of dataset size (SPEC-00
§1.2). It is parameterized by the same org-filter branching described
above (mirrors `DashboardMetricsService`'s scoping, not a second
ad-hoc implementation) and by a report `type` (`enrollments`,
`certificates`, ...). A Gestor's `org_id` is always resolved from
`$user->org_id` server-side and never trusted from request input — a
Gestor passing `?org_id=<anotherOrg>` must 403, not silently scope to
their own org (see `dashboard-conventions` for the exact guard).

## Relationship to Other Modules

- Reuses `HelpArticleResolverService`'s org-then-global fallback shape
  (see `help-architecture`) as the template for `SettingService`, but
  against the sentinel-PK `system_settings` schema rather than a
  nullable-`org_id` table.
- Reuses `FileUploadService` (see `courses-conventions`) for the
  settings screen's logo upload, the same convention as `Organization`'s
  `logo_path` field.
- The dashboard's `recentEnrollments` shape (`student_name`,
  `course_name`, `status_label`, `status_badge_variant`) is presentation
  data computed by the service, not a raw `course_user` row — do not
  leak pivot column names (`status`, `progress_percentage`) into the
  view; translate them in `DashboardMetricsService`.
