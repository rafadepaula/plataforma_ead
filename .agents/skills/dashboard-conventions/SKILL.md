---
name: dashboard-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Admin
  Dashboard, Analytics & System Settings feature (SPEC-12): the
  `admin.dashboard`/`reports.export`/`settings.edit`/`settings.update`
  route-name contract, the `<x-ui.stat-card>`/`<x-ui.table>`/
  `<x-ui.badge>`-only Blade composition (no new UI components), the
  `dusk="stat-{metric}"`/`dusk="export-{type}-csv"` test-selector
  contract, and the Gestor-cannot-pass-`org_id` export guard. Use
  whenever writing a controller, service, Blade view, or test that
  touches the Dashboard, the CSV export, or the `system_settings`
  org-override screen.
license: MIT
metadata:
  feature: dashboard
  role: conventions
  specs:
    - spec/specs/12-admin-dashboard-analytics-and-system-settings.md
    - spec/docs/mockups/07-dashboard-admin.md
---

# Dashboard Conventions

## Route Names Are Load-Bearing

`components/layout/sidebar.blade.php` resolves the Dashboard link via
`Route::has('admin.dashboard') ? route('admin.dashboard') : '#'` — it
does **not** error if the name is missing or different, it silently
degrades to a dead `#` link. Register the route with this **exact**
name:

```php
Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->name('admin.dashboard');
```

The CSV export and settings routes have no such defensive fallback
elsewhere in the codebase, but keep the same naming discipline so tests
and views agree:

```php
Route::get('/admin/reports/{type}/export', [ReportExportController::class, 'stream'])
    ->name('reports.export');

Route::get('/admin/settings', [SystemSettingController::class, 'edit'])
    ->name('settings.edit');
Route::put('/admin/settings', [SystemSettingController::class, 'update'])
    ->name('settings.update');
```

All 3 route groups are `role:admin|gestor` — no dedicated Policy class,
mirroring the `quiz-attempts.pending`/`forum-moderation.index`
role-middleware-only precedent (see `quizzes-conventions`).

## Dashboard View: Only Pre-Existing UI Components

`resources/views/dashboard/index.blade.php` composes exclusively from
`<x-ui.stat-card>`, `<x-ui.table>`, and `<x-ui.badge>` — no new component
is needed, and none should be added for this screen. Follow the
mockup's exact Blade shape (`spec/docs/mockups/07-dashboard-admin.md`
§3):

```blade
<x-ui.stat-card kicker="Alunos ativos" value="{{ $stats['active_students'] }}" delta="+4,2%" dusk="stat-active-students" />
```

`$stats` is a plain array (`stats['active_students']`, not an object).
`$recentEnrollments` entries may be a plain array or an Eloquent-model-ish
shape — read them in the view with `data_get($enrollment, 'student_name')`
rather than `$enrollment->student_name`/`$enrollment['student_name']`
directly, so the view does not need to know or care which
`DashboardMetricsService` returns.

## `dusk` Selector Contract

| Element | Selector |
| --- | --- |
| Dashboard root container | `dusk="admin-dashboard"` |
| Each stat card | `dusk="stat-active-students"`, `dusk="stat-certificates-issued"`, `dusk="stat-completion-rate"`, `dusk="stat-courses-count"` |
| Recent enrollments table | `dusk="recent-enrollments-table"` |
| CSV export links | `dusk="export-{type}-csv"` (e.g. `export-enrollments-csv`, `export-certificates-csv`) |
| Settings form | `dusk="settings-form"` / `dusk="settings-submit"` |

Keep these stable — `DashboardDuskTest`/`OrgDashboardTest` assert against
them directly, and a rename without updating both tests will look like a
silent regression rather than an intentional rename.

## CSV Export Entry Point: Plain `<a>`, No JS Module

The dashboard's export links are plain downloads:

```blade
<a href="{{ route('reports.export', ['type' => 'enrollments']) }}" dusk="export-enrollments-csv">
    Exportar Matrículas (CSV)
</a>
```

No `resources/js/modules/*.js` module is needed for this — only add one
(following `ModuleReorder.js`/`SmartInvitationForm.js`'s SOLID-module
convention) if a future iteration adds a type-picker/date-range form
that needs client-side behavior beyond a static link.

## Gestor Cannot Pass `org_id` for Another Org

`DashboardController`, `ReportExportController`, and
`SystemSettingController` each carry their own **identical**
`protected function resolveViewingOrgId(Request $request): ?int`
method (Admin: `session('active_org_id')` or `null` for global; anyone
else: `$user->org_id`) — this is a deliberate 3-way duplication, **not**
`App\Http\Controllers\Concerns\ResolvesOrgContext` (that trait is
RF04/RF05's Aluno/Gestor-CRUD helper, used by `UserController`/
`UserImportController`; it `throw`s `UnresolvedOrgContextException`
when unresolved instead of returning `null`, which is the wrong contract
for an Admin's legitimate "global, no Org" dashboard view). If a future
change consolidates these 3 copies into a shared trait, it must preserve
the nullable-for-global-Admin return value, not adopt
`ResolvesOrgContext`'s throwing behavior.

`ReportExportController@stream` additionally guards against a
non-Admin's spoofed `org_id` query parameter — the actual guard checks
"not Admin", not "is Gestor" specifically (the route is
`role:admin|gestor` so in practice this only ever fires for a Gestor,
but read the condition as written):

```php
if (! $user->hasRole(RolesEnum::ADMIN->value) && $request->filled('org_id')
    && (int) $request->query('org_id') !== (int) $user->org_id) {
    abort(403);
}
```

An Admin's request MAY legitimately pass no `org_id` at all (global) or
rely purely on `session('active_org_id')` set via Impersonate Org — the
spec text implies Impersonate Org is the only sanctioned way for an
Admin to scope to one Org (no separate `?org_id=` query-param UX), so do
not add a second Admin-only org-selection affordance without confirming
against the open question logged in `dashboard-maintenance`.

## Settings Form Field Names

`resources/views/settings/edit.blade.php` posts these field names to
`UpdateSystemSettingRequest` — keep the Form Request's validation rules
and the view's `name="..."` attributes in sync:

`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password` (optional —
blank means "keep current"), `logo` (file upload, reuses
`FileUploadService`, see `courses-conventions`), `signature` (plain
text/textarea, stored verbatim as the certificate signature override).
