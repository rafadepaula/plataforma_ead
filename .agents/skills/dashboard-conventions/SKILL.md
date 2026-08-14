---
name: dashboard-conventions
description: >
  Code patterns, snippets, guardrails for Admin Dashboard, Analytics & System
  Settings feature (SPEC-12): `admin.dashboard`/`reports.export`/
  `settings.edit`/`settings.update` route-name contract,
  `<x-ui.stat-card>`/`<x-ui.table>`/`<x-ui.badge>`-only Blade composition (no new
  UI components), `dusk="stat-{metric}"`/`dusk="export-{type}-csv"`
  test-selector contract, Gestor-cannot-pass-`org_id` export guard, and
  SPEC-001's `organizations-summary-table`/`organization-summary-row-{id}`/
  `org-summary-{metric}-{id}` selector contract. Use when write controller,
  service, Blade view, or test touching Dashboard, CSV export, or
  `system_settings` org-override screen.
license: MIT
metadata:
  feature: dashboard
  role: conventions
  specs:
    - spec/specs/12-admin-dashboard-analytics-and-system-settings.md
    - spec/docs/mockups/07-dashboard-admin.md
---

# Dashboard Conventions

## Route Names Load-Bearing

`components/layout/sidebar.blade.php` resolve Dashboard link via
`Route::has('admin.dashboard') ? route('admin.dashboard') : '#'`. It does **not**
error if name missing or different, it silently degrade to dead `#` link.
Register route with this **exact** name:

```php
Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->name('admin.dashboard');
```

CSV export and settings routes have no such defensive fallback elsewhere in
codebase, but keep same naming discipline so tests and views agree:

```php
Route::get('/admin/reports/{type}/export', [ReportExportController::class, 'stream'])
    ->name('reports.export');

Route::get('/admin/settings', [SystemSettingController::class, 'edit'])
    ->name('settings.edit');
Route::put('/admin/settings', [SystemSettingController::class, 'update'])
    ->name('settings.update');
```

All 3 route groups `role:admin|gestor`. No dedicated Policy class, mirroring
`quiz-attempts.pending`/`forum-moderation.index` role-middleware-only precedent
(see `quizzes-conventions`).

## Dashboard View: Only Pre-Existing UI Components

`resources/views/dashboard/index.blade.php` compose exclusively from
`<x-ui.stat-card>`, `<x-ui.table>`, and `<x-ui.badge>`. No new component needed,
and none should be added for this screen. Follow mockup exact Blade shape
(`spec/docs/mockups/07-dashboard-admin.md` §3):

```blade
<x-ui.stat-card kicker="Alunos ativos" value="{{ $stats['active_students'] }}" delta="+4,2%" dusk="stat-active-students" />
```

`$stats` plain array (`stats['active_students']`, not object).
`$recentEnrollments` entries may be plain array or Eloquent-model-ish shape. Read
them in view with `data_get($enrollment, 'student_name')` rather than
`$enrollment->student_name`/`$enrollment['student_name']` directly, so view need
not know or care which `DashboardMetricsService` return.

## `dusk` Selector Contract

| Element | Selector |
| --- | --- |
| Dashboard root container | `dusk="admin-dashboard"` |
| Each stat card | `dusk="stat-active-students"`, `dusk="stat-certificates-issued"`, `dusk="stat-completion-rate"`, `dusk="stat-courses-count"` |
| Recent enrollments table | `dusk="recent-enrollments-table"` |
| CSV export links | `dusk="export-{type}-csv"` (e.g. `export-enrollments-csv`, `export-certificates-csv`) |
| Settings form | `dusk="settings-form"` / `dusk="settings-submit"` |
| Organizations summary table (SPEC-001, Admin-global-only) | `dusk="organizations-summary-table"` |
| Organizations summary row | `dusk="organization-summary-row-{id}"` |
| Organizations summary per-metric cell | `dusk="org-summary-students-{id}"` / `dusk="org-summary-courses-{id}"` / `dusk="org-summary-certificates-{id}"` |

Keep these stable. `DashboardDuskTest`/`OrgDashboardTest` assert against them
directly, and rename without updating both tests look like silent regression
rather than intentional rename.

## Organizations Summary Table: Reuse `$isGlobalAdminView`, Literal Blade Strings

`DashboardController@index` resolves `$isGlobalAdminView` once and passes
`organizationsSummary` as `null` when it's `false` — the view gates the whole
block with `@isset($organizationsSummary)`. Do not re-derive
role/impersonation state in the view or a new consumer; reuse the controller's
resolved value (see `dashboard-architecture`).

`resources/views/dashboard/index.blade.php` reuses these exact literal
strings — keep them if you touch this block:

```blade
<h2 class="h5 mb-0">Resumo das Organizações</h2>
...
:headers="['Organização', 'Alunos', 'Cursos', 'Certificados']"
```

## CSV Export Entry Point: Plain `<a>`, No JS Module

Dashboard export links plain downloads:

```blade
<a href="{{ route('reports.export', ['type' => 'enrollments']) }}" dusk="export-enrollments-csv">
    Exportar Matrículas (CSV)
</a>
```

No `resources/js/modules/*.js` module needed for this. Only add one (following
`ModuleReorder.js`/`SmartInvitationForm.js` SOLID-module convention) if future
iteration add type-picker/date-range form needing client-side behavior beyond
static link.

## Gestor Cannot Pass `org_id` for Another Org

`DashboardController`, `ReportExportController`, and `SystemSettingController`
each carry own **identical**
`protected function resolveViewingOrgId(Request $request): ?int` method (Admin:
`session('active_org_id')` or `null` for global; anyone else: `$user->org_id`).
Deliberate 3-way duplication, **not**
`App\Http\Controllers\Concerns\ResolvesOrgContext` (that trait RF04/RF05's
Aluno/Gestor-CRUD helper, used by `UserController`/`UserImportController`; it
`throw`s `UnresolvedOrgContextException` when unresolved instead of returning
`null`, wrong contract for Admin's legitimate "global, no Org" dashboard view).
If future change consolidate these 3 copies into shared trait, it must preserve
nullable-for-global-Admin return value, not adopt `ResolvesOrgContext` throwing
behavior.

`ReportExportController@stream` additionally guard against non-Admin's spoofed
`org_id` query parameter. Actual guard check "not Admin", not "is Gestor"
specifically (route is `role:admin|gestor` so in practice this only ever fire for
Gestor, but read condition as written):

```php
if (! $user->hasRole(RolesEnum::ADMIN->value) && $request->filled('org_id')
    && (int) $request->query('org_id') !== (int) $user->org_id) {
    abort(403);
}
```

Admin request MAY legitimately pass no `org_id` at all (global) or rely purely on
`session('active_org_id')` set via Impersonate Org. Spec text imply Impersonate
Org only sanctioned way for Admin to scope to one Org (no separate `?org_id=`
query-param UX), so do not add second Admin-only org-selection affordance without
confirming against open question logged in `dashboard-maintenance`.

## Settings Form Field Names

`resources/views/settings/edit.blade.php` post these field names to
`UpdateSystemSettingRequest`. Keep Form Request validation rules and view
`name="..."` attributes in sync:

`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password` (optional — blank mean
"keep current"), `logo` (file upload, reuse `FileUploadService`, see
`courses-conventions`), `signature` (plain text/textarea, stored verbatim as
certificate signature override).
