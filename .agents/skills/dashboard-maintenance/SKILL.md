---
name: dashboard-maintenance
description: >
  Debugging, testing, and edge-case guide for the Admin Dashboard,
  Analytics & System Settings feature (SPEC-12): the mandatory
  PHPUnit/Dusk test files, common org-scoping/streaming failure modes,
  the `admin.dashboard` route-name gotcha, and the open questions
  (exact KPI/CSV column definitions, SMTP admin-only-vs-gestor-editable)
  that still need a decision. Use when `OrgDashboardTest`,
  `MultiTenantCsvExportTest`, or `DashboardDuskTest` is failing; a KPI
  shows the wrong (cross-org) number; a CSV export silently buffers
  instead of streaming; or the Dashboard sidebar link stays dead (`#`).
license: MIT
metadata:
  feature: dashboard
  role: maintenance
  specs:
    - spec/specs/12-admin-dashboard-analytics-and-system-settings.md
    - spec/docs/mockups/07-dashboard-admin.md
---

# Dashboard Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-12 contract and must stay green (PHPUnit, no
Pest):

- `tests/Unit/Services/SettingServiceTest.php` — org-then-global
  fallback + cache-busting on `set()`/`forget()`.
- `tests/Unit/Services/DashboardMetricsServiceTest.php` — the exact stat
  shape and the manual admin-global-vs-gestor-own-org branching for
  `Certificate`/`course_user`/`User` (see `dashboard-architecture`).
- `tests/Feature/OrgDashboardTest.php` — an Admin with no impersonated
  Org sees global KPIs/recentEnrollments, an Admin impersonating an Org
  sees only that Org's, and a Gestor sees only their own `org_id`'s.
- `tests/Feature/MultiTenantCsvExportTest.php` — the export is a genuine
  `StreamedResponse` (not a buffered string) with correct
  `Content-Disposition`, and the row-scoping mirrors
  `OrgDashboardTest`'s 3 cases.
- `tests/Browser/DashboardDuskTest.php` (Dusk E2E) — Admin dashboard
  render + KPI values + recent-enrollments table, Gestor's scoped view,
  the CSV export link's `href`, and the settings edit screen persisting
  an org override.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=OrgDashboardTest
vendor/bin/sail artisan test --filter=MultiTenantCsvExportTest
vendor/bin/sail dusk --filter=DashboardDuskTest
```

Dusk tests use `DatabaseMigrations`, never `RefreshDatabase` (Dusk runs
in a separate HTTP process against the same DB connection) — see
`laravel-dusk`.

## Common Failure Modes

- **Dashboard sidebar link stays `#` / 404s.** The route name must be
  **exactly** `admin.dashboard` —
  `components/layout/sidebar.blade.php` checks `Route::has('admin.dashboard')`
  and degrades silently to `#` rather than raising an error, so a typo'd
  or renamed route produces a dead link with no exception to catch it.
- **A KPI/recent-enrollment row leaks another Organization's data for a
  Gestor, or an Admin impersonating an Org.** `Certificate`, `course_user`,
  and `User` do **not** carry `OrgScope` — check that
  `DashboardMetricsService` explicitly joins through `courses.org_id`
  (or filters via the already-`OrgScope`d `Course` query first) rather
  than assuming a global scope narrows these tables automatically; see
  `dashboard-architecture`'s "cannot just rely on `OrgScope`" section.
- **An Admin with no active Impersonate Org session sees a single Org's
  numbers instead of the global total (or vice versa).** `OrgScope`
  itself resolves "admin + no `active_org_id` in session => no `WHERE`
  clause" for free on `Course`/`InvitationLink`, but
  `DashboardMetricsService`'s raw `Certificate`/`course_user`/`User`
  queries must replicate that exact branch manually — a missing `if`
  here is the most common cause of `OrgDashboardTest`'s global-KPI case
  failing while the scoped case still passes.
- **`MultiTenantCsvExportTest` passes but the export actually buffers
  the whole dataset first.** Grep the export service/action for `->get()`
  followed by a loop over an in-memory `Collection` — that defeats the
  O(1)-RAM contract even if the response type still happens to be a
  `StreamedResponse`. The correct shape is `chunk()`/`lazy()` **inside**
  the `streamDownload()` callback, writing each row with `fputcsv()` as
  it is fetched, never collecting rows into an array first.
- **A Gestor's export request with a spoofed `?org_id=` query param
  returns another Org's rows instead of 403ing.** The controller must
  resolve a Gestor's org strictly from `$user->org_id`, never trust
  `$request->query('org_id')` for that role — see
  `dashboard-conventions`'s exact guard snippet.
- **Dusk's CSV export assertion cannot detect the actual downloaded
  file.** Headless Chrome download verification is out of scope for this
  suite's current coverage — `DashboardDuskTest` asserts the export
  `<a>`'s `href` resolves to the correct `reports.export` URL rather than
  inspecting a downloaded file on disk; do not add filesystem-download
  assertions without also updating the Dusk browser's download
  preferences in `DuskTestCase`.

## Open Questions Still Needing a Decision

These are logged from the SPEC-12 tech-refine pass and are **not**
resolved by this bucket's implementation — flag them again before
building on top of assumptions baked into the current code:

1. **Exact KPI/CSV column definitions.** The mockup
   (`spec/docs/mockups/07-dashboard-admin.md` §4) only shows 4 stat
   values + a 3-column recent-enrollments table with no field list for
   the CSV itself, and no historical-comparison logic for the `delta`
   percentages (`+4,2%`/`+12%` are hardcoded display strings in the
   mockup, not computed) — confirm whether real delta computation is
   in scope before treating the hardcoded mockup values as final.
2. **CSV report types.** Only `enrollments` and `certificates` are
   wired by this bucket's dashboard entry points; confirm whether a
   `users` report or others are also expected under "Central de
   Exportação" before assuming the `type` route parameter's valid set
   is closed.
3. **SMTP settings: admin-only or gestor-editable per-org?** Not
   addressed by the spec text — a compromised Gestor account setting
   arbitrary SMTP credentials is a security concern. The current
   `settings.edit`/`settings.update` routes are gated `role:admin|gestor`
   uniformly; if SMTP fields should in fact be Admin-only, that requires
   a follow-up field-level authorization check in
   `UpdateSystemSettingRequest`/`SystemSettingController`, not a route
   middleware change (logo/signature should almost certainly remain
   Gestor-editable per-org either way).
4. **Admin per-Org dashboard access: Impersonate Org only, or also a
   `?org_id=` query param?** The spec text's "recurso de Impersonate Org
   para visualizar dashboards de Orgs específicas" suggests Impersonate
   Org is the sole sanctioned path; `DashboardController`/
   `ReportExportController` should not grow a parallel Admin-facing
   `org_id` query-param affordance without confirming this.
