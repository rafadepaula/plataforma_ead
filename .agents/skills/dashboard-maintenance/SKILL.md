---
name: dashboard-maintenance
description: >
  Debug, test, edge-case guide for Admin Dashboard, Analytics & System
  Settings (SPEC-12): mandatory PHPUnit/Dusk test files, common
  org-scoping/streaming failure modes, `admin.dashboard` route-name
  gotcha, open questions (exact KPI/CSV column definitions, SMTP
  admin-only-vs-gestor-editable) still needing decision. Also covers
  SPEC-001's Organizations summary table test coverage (3
  `OrgDashboardTest` cases, 4 `DashboardMetricsServiceTest` cases,
  `DashboardDuskTest` assertions). Use when `OrgDashboardTest`,
  `MultiTenantCsvExportTest`, or `DashboardDuskTest` fail; KPI show wrong
  (cross-org) number; CSV export silently buffer instead of stream;
  Dashboard sidebar link stay dead (`#`); or the Organizations summary
  table shows/hides for the wrong actor.
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

Tests guard SPEC-12 contract. Must stay green (PHPUnit, no Pest):

- `tests/Unit/Services/SettingServiceTest.php` — org-then-global fallback
  + cache-busting on `set()`/`forget()`.
- `tests/Unit/Services/DashboardMetricsServiceTest.php` — exact stat shape
  and manual admin-global-vs-gestor-own-org branching for
  `Certificate`/`course_user`/`User` (see `dashboard-architecture`); plus
  4 SPEC-001 cases for `organizationsSummary()`:
  `test_organizations_summary_counts_active_alunos_courses_and_non_revoked_certificates_per_org`,
  `test_organizations_summary_bypasses_courses_org_scope_regardless_of_the_acting_user`,
  `test_organizations_summary_zero_fills_an_organization_with_no_related_data`,
  `test_organizations_summary_excludes_soft_deleted_organizations`.
- `tests/Feature/OrgDashboardTest.php` — Admin with no impersonated Org
  see global KPIs/recentEnrollments, Admin impersonating Org see only
  that Org, Gestor see only own `org_id`; plus 3 SPEC-001 cases for the
  Organizations summary table:
  `test_admin_with_no_impersonated_org_sees_organizations_summary_with_correct_counts`,
  `test_gestor_never_receives_organizations_summary`,
  `test_admin_impersonating_an_org_does_not_receive_organizations_summary`.
- `tests/Feature/MultiTenantCsvExportTest.php` — export is genuine
  `StreamedResponse` (not buffered string) with correct
  `Content-Disposition`, row-scoping mirror `OrgDashboardTest` 3 cases.
- `tests/Browser/DashboardDuskTest.php` (Dusk E2E) — Admin dashboard
  render + KPI values + recent-enrollments table, Gestor scoped view, CSV
  export link `href`, settings edit screen persisting org override; plus
  `@organizations-summary-table` present in the Admin-global lifecycle
  and `assertMissing` in the Gestor (and Admin-impersonating) lifecycle.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=OrgDashboardTest
vendor/bin/sail artisan test --filter=MultiTenantCsvExportTest
vendor/bin/sail dusk --filter=DashboardDuskTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk run in separate
HTTP process); `DatabaseMigrations` retired (per-method `migrate:fresh`)
— see `laravel-dusk`/`testing-conventions`.

## Common Failure Modes

- **Dashboard sidebar link stay `#` / 404.** Route name must be
  **exactly** `admin.dashboard` —
  `components/layout/sidebar.blade.php` check `Route::has('admin.dashboard')`
  and degrade silently to `#` rather than raise error, so typo'd or
  renamed route produce dead link with no exception to catch it.
- **KPI/recent-enrollment row leak another Organization data for Gestor,
  or Admin impersonating Org.** `Certificate`, `course_user`, `User` do
  **not** carry `OrgScope` — check `DashboardMetricsService` explicitly
  join through `courses.org_id` (or filter via already-`OrgScope`d
  `Course` query first) rather than assume global scope narrow these
  tables automatically; see `dashboard-architecture` "cannot just rely on
  `OrgScope`" section.
- **Admin with no active Impersonate Org session see single Org numbers
  instead of global total (or reverse).** `OrgScope` itself resolve
  "admin + no `active_org_id` in session => no `WHERE` clause" for free
  on `Course`/`InvitationLink`, but `DashboardMetricsService` raw
  `Certificate`/`course_user`/`User` queries must replicate that exact
  branch manually — missing `if` here is most common cause of
  `OrgDashboardTest` global-KPI case failing while scoped case still
  pass.
- **`MultiTenantCsvExportTest` pass but export actually buffer whole
  dataset first.** Grep export service/action for `->get()` followed by
  loop over in-memory `Collection` — that defeat O(1)-RAM contract even
  if response type still happen to be `StreamedResponse`. Correct shape
  is `chunk()`/`lazy()` **inside** `streamDownload()` callback, writing
  each row with `fputcsv()` as fetched, never collecting rows into array
  first.
- **Gestor export request with spoofed `?org_id=` query param return
  another Org rows instead of 403.** Controller must resolve Gestor org
  strictly from `$user->org_id`, never trust `$request->query('org_id')`
  for that role — see `dashboard-conventions` exact guard snippet.
- **Organizations summary table appears for Gestor, or for an Admin
  impersonating an Org (or is missing for a true global Admin).** The
  gate is `$isGlobalAdminView` computed once in `DashboardController@index`
  (`Admin role AND resolveViewingOrgId() === null`) — check that value,
  not a fresh role/session read, and check the view still wraps the
  block in `@isset($organizationsSummary)` rather than always rendering
  it. See `dashboard-architecture` "Organizations Summary Table" section.
- **Dusk CSV export assertion cannot detect actual downloaded file.**
  Headless Chrome download verification out of scope for this suite
  current coverage — `DashboardDuskTest` assert export `<a>` `href`
  resolve to correct `reports.export` URL rather than inspect downloaded
  file on disk; do not add filesystem-download assertions without also
  updating Dusk browser download preferences in `DuskTestCase`.

## Open Questions Still Needing a Decision

Logged from SPEC-12 tech-refine pass. **Not** resolved by this bucket
implementation — flag again before building on top of assumptions baked
into current code:

1. **Exact KPI/CSV column definitions.** Mockup
   (`spec/docs/mockups/07-dashboard-admin.md` §4) show only 4 stat values
   + 3-column recent-enrollments table with no field list for CSV itself,
   and no historical-comparison logic for `delta` percentages
   (`+4,2%`/`+12%` are hardcoded display strings in mockup, not computed)
   — confirm whether real delta computation in scope before treat
   hardcoded mockup values as final.
2. **CSV report types.** Only `enrollments` and `certificates` wired by
   this bucket dashboard entry points; confirm whether `users` report or
   others also expected under "Central de Exportação" before assume
   `type` route parameter valid set is closed.
3. **SMTP settings: admin-only or gestor-editable per-org?** Not
   addressed by spec text — compromised Gestor account setting arbitrary
   SMTP credentials is security concern. Current
   `settings.edit`/`settings.update` routes gated `role:admin|gestor`
   uniformly; if SMTP fields must be Admin-only, that require follow-up
   field-level authorization check in
   `UpdateSystemSettingRequest`/`SystemSettingController`, not route
   middleware change (logo/signature almost certainly remain
   Gestor-editable per-org either way).
4. **Admin per-Org dashboard access: Impersonate Org only, or also
   `?org_id=` query param?** Spec text "recurso de Impersonate Org para
   visualizar dashboards de Orgs específicas" suggest Impersonate Org is
   sole sanctioned path; `DashboardController`/`ReportExportController`
   must not grow parallel Admin-facing `org_id` query-param affordance
   without confirming this.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module, spec, or use case. Consequences when
maintain this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
