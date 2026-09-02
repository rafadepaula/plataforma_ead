---
name: audit-logs-conventions
description: >
  Code patterns and guardrails for System Audit Logging & Monitoring:
  `AuditService::log()` call-site pattern, redaction list,
  `admin.audit-logs.index`/`gestor.audit-logs.index`/`*.export` route
  contract, Blade/JS diff-modal wiring, CSV export streaming. Use when
  writing controller, service, observer, listener, Blade view, or JS that
  touches `AuditLog` rows or the `/admin|gestor/audit-logs` screens.
license: MIT
metadata:
  feature: audit-logs
  role: conventions
---

# Audit Logs Conventions

## `AuditService::log()` Call Site

```php
AuditService::log(
    event: 'impersonate.start',
    orgId: $targetOrg->id,
    userId: $admin->id,
    payload: [
        'admin_id' => $admin->id,
        'target_org_id' => $targetOrg->id,
        'target_org_name' => $targetOrg->name,
    ],
);
```

Wrap every call site in `try`/`catch` that swallows (same shape as `SendEnrollmentConfirmedNotification`, see `notifications-conventions`) so audit failure **never** breaks the primary flow:

```php
try {
    AuditService::log(event: 'certificate.issued', /* ... */);
} catch (\Throwable $e) {
    report($e);
}
```

Inside `AuditService::log()`, only the **DB** write is try/catch-wrapped. Monolog write must still run when DB write fails, and reverse — duplo-armazenamento contract in `audit-logs-architecture`.

## Redaction

`password` and `remember_token` stripped from **both** `old_values`/`new_values` unconditionally, ignoring `$hidden`/casts. `unset()` both keys explicitly after building arrays from `getChanges()`/`getOriginal()`. Do not use `Arr::except()` against model `$hidden` — `$hidden` governs JSON serialization, not `getChanges()`. Model may extend with `protected array $auditRedact = [...]`, merged by `AuditObserver` into base `['password', 'remember_token']`.

Auth payloads (`login.success`, `login.failed`, `password.reset`) always carry literal `'password' => '[REDACTED]'` — fixed string, not a redacted real value. Never interpolate `$request->input('password')` near an audit call, not even transiently.

## Route Contract

Two prefixes, same controller methods:

```php
Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('admin/audit-logs/export', [AuditLogController::class, 'export'])->name('admin.audit-logs.export');
});

Route::middleware(['auth', 'role:gestor'])->group(function (): void {
    Route::get('gestor/audit-logs', [AuditLogController::class, 'index'])->name('gestor.audit-logs.index');
    Route::get('gestor/audit-logs/export', [AuditLogController::class, 'export'])->name('gestor.audit-logs.export');
});
```

`components/layout/sidebar.blade.php` picks the name for current user (`gestor`-only account gets `gestor.audit-logs.index`; anyone else, Admin included, gets `admin.audit-logs.index`) via `Route::has()` guards, like every other sidebar entry. Renaming either route means updating sidebar + `AuditLogUiTest` + `AuditLogTest` together.

`audit-logs/index.blade.php` derives the export route from the **current** route at render time (`str_replace('.index', '.export', request()->route()->getName())`), never hardcodes `admin.audit-logs.export`. One Blade serves both prefixes, no role conditional in view.

## View-Data Contract (`AuditLogController::index()`)

```php
return view('audit-logs.index', [
    'auditLogs' => $query->with('user')->latest('created_at')->paginate(25)->withQueryString(),
    'organizations' => $user->hasRole('admin') ? Organization::pluck('name', 'id') : null,
    'eventCategories' => [
        'authentication' => 'Autenticação',
        'mutations' => 'Mutações de Banco',
        'critical_actions' => 'Ações Críticas',
    ],
]);
```

Filters from request:
- `date_from`/`date_to` — `whereDate('created_at', '>='/'<=', ...)`, not `whereBetween`.
- `org_id` — Admin only. **Must** be stripped for non-Admin even when present in query string, like `ReportExportController`'s spoofed-`org_id` guard (`dashboard-conventions`).
- `event_category` — `authentication`/`critical_actions` map to explicit `EVENT_CATEGORIES` list of concrete `event` values. `mutations` is the exception: `event LIKE '%.created'/'%.updated'/'%.deleted'` plus `whereNotIn` against the `critical_actions` list, because it covers every opted-in model's `{ModelFQCN}.created` and cannot be enumerated.
- `user_search` — `whereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))`.

## CSV Export Streaming

`export()` reuses `index()` filter logic — extract to private `applyFilters()` so the two cannot drift. Streams full filtered set, not current page: follow `CsvStreamExportService`'s `chunk()`/`lazy()` inside `streamDownload()` (see `dashboard-architecture` CSV Streaming Contract). `->paginate()->items()` silently truncates export to one page.

## Diff Modal: One Shared Modal

Unlike `forum/partials/_edit-history-modal.blade.php` (one modal per post), `audit-logs/partials/_diff-modal.blade.php` is a **single** shared `#audit-diff-modal` reused by every row. Each "Ver diff" button inlines its own diff as JSON attributes:

```blade
<button type="button"
        data-modal-target="audit-diff-modal"
        data-audit-diff-trigger
        data-event="{{ $log->event }}"
        data-old-values="{{ json_encode($log->old_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
        data-new-values="{{ json_encode($log->new_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
        dusk="view-diff-{{ $log->id }}">
    Ver diff
</button>
```

`resources/js/modules/AuditLogDiffModal.js` reads attributes on click, writes into shared modal's `[dusk="audit-diff-old"]`/`[dusk="audit-diff-new"]` `<pre>` blocks, then opens it imperatively with `bootstrap.Modal.getOrCreateInstance()` (the trigger keeps the legacy `data-modal-target` spelling on purpose, so Bootstrap's delegated handler cannot open the modal before the panes are filled). JS-only render, no AJAX. Do not add per-row `<x-ui.modal>` — 25 duplicated modals per page is what this avoids.

`index.blade.php` renders the results as **two** variants per the `bootstrap-conventions` desktop-table/mobile-card responsive split (`d-none d-md-block` table below `md`, `d-md-none` card list above it, same `#audit-diff-modal`). The mobile "Ver diff" trigger carries the identical `data-modal-target`/`data-audit-diff-trigger`/`data-event`/`data-old-values`/`data-new-values` dataset so `AuditLogDiffModal.js` needs no branching — but it drops `dusk="view-diff-{id}"` (dusk selectors live on the desktop variant only, never duplicated). Do not add `dusk` to the mobile trigger.

## `dusk` Selector Contract

| Element | Selector |
| --- | --- |
| Page root | `dusk="audit-logs-index"` |
| Filter form | `dusk="audit-logs-filter-form"` |
| Filter inputs | `dusk="audit-logs-date-from"`, `-date-to`, `-org-filter`, `-event-filter`, `-user-filter` |
| Results table | `dusk="audit-logs-table"` |
| Row | `dusk="audit-log-row-{id}"` |
| "Ver diff" button | `dusk="view-diff-{id}"` |
| Diff modal fields | `dusk="audit-diff-event"`, `dusk="audit-diff-old"`, `dusk="audit-diff-new"` |
| CSV export link | `dusk="export-audit-logs-csv"` |
| Sidebar link | `dusk="sidebar-audit-logs-link"`, `dusk="sidebar-audit-logs-link-mobile"` |
