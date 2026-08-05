---
name: audit-logs-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the System Audit
  Logging & Monitoring feature (SPEC-15): the `AuditService::log()`
  call-site pattern, the sensitive-field redaction list, the
  `admin.audit-logs.index`/`gestor.audit-logs.index`/`*.export`
  route-name contract, the Blade/JS diff-modal wiring, and the CSV
  export streaming pattern. Use whenever writing a controller, service,
  observer, listener, Blade view, or JS module that touches `AuditLog`
  rows or the `/admin|gestor/audit-logs` screens.
license: MIT
metadata:
  feature: audit-logs
  role: conventions
  specs:
    - spec/specs/15-system-audit-logging-and-monitoring.md
---

# Audit Logs Conventions

## `AuditService::log()` Call-Site Pattern

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

Wrap every call site in a `try`/`catch` that swallows the exception
(mirroring how `SendEnrollmentConfirmedNotification` wraps risky
side-effect calls, see `notifications-conventions`) so an audit-logging
failure **never** breaks the primary flow it is attached to:

```php
try {
    AuditService::log(event: 'certificate.issued', /* ... */);
} catch (\Throwable $e) {
    report($e);
}
```

`AuditService::log()` itself also internally wraps only its **DB**
write in try/catch — the Monolog write must not be skipped just because
the DB write failed, and vice versa; both are attempted independently
per `audit-logs-architecture`'s duplo-armazenamento contract.

## Redaction List

`password` and `remember_token` are stripped from **both**
`old_values`/`new_values` unconditionally, regardless of `$hidden`/casts
— `unset()` both keys explicitly after building the arrays from
`getChanges()`/`getOriginal()`, do not rely on `Arr::except()` against a
model's `$hidden` list (a model's own `$hidden` governs JSON
serialization, not what `getChanges()` returns). A model may extend the
redaction set with its own `protected array $auditRedact = [...]`
property, merged into the base `['password', 'remember_token']` list by
`AuditObserver`.

Auth-event payloads (`login.success`, `login.failed`, `password.reset`)
always include a literal `'password' => '[REDACTED]'` placeholder key —
this is a fixed string in the payload, not a redacted real value, so
never interpolate `$request->input('password')` anywhere near an audit
call site even transiently.

## Route-Name Contract

Two distinct prefixes, same controller methods:

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

`components/layout/sidebar.blade.php` resolves whichever of the two
names applies to the current user (`gestor`-only account →
`gestor.audit-logs.index`; anyone else, including an Admin, →
`admin.audit-logs.index`) via `Route::has()` guards, exactly like every
other sidebar entry — do not rename either route without updating the
sidebar and `AuditLogUiTest`/`AuditLogTest` together.

`audit-logs/index.blade.php` derives the export route name from the
**current** route at render time (`str_replace('.index', '.export',
request()->route()->getName())`) rather than hard-coding
`admin.audit-logs.export`, so the same Blade file serves both prefixes
without a role-conditional in the view.

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

Query filters read from the request: `date_from`/`date_to` (applied via
`whereDate('created_at', '>='/'<=', ...)`, not `whereBetween`), `org_id`
(Admin only — **must** be ignored/stripped for a non-Admin request even
if present in the query string, mirroring `ReportExportController`'s
spoofed-`org_id` guard, see `dashboard-conventions`), `event_category`
(`authentication`/`critical_actions` map to an explicit `EVENT_CATEGORIES`
list of concrete `event` values; `mutations` is the one exception —
matched via `event LIKE '%.created'/'%.updated'/'%.deleted'` plus a
`whereNotIn` against the `critical_actions` list, since it covers every
`AuditableTrait`-opted-in model's `{ModelFQCN}.created` event and cannot
be enumerated as a fixed list), `user_search` (matches `whereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))`).

## CSV Export Streaming Pattern

`AuditLogController::export()` reuses the **same** filter-building logic
as `index()` (extract to a private `applyFilters()` helper so the two
methods cannot drift) but streams the full filtered result set, not just
the current page — follow `CsvStreamExportService`'s `chunk()`/`lazy()`-
inside-`streamDownload()` shape (see `dashboard-architecture`'s CSV
Streaming Contract) rather than `->paginate()->items()`, which would
silently truncate the export to one page.

## Diff Modal: Single Shared Modal, Not One Per Row

Unlike `forum/partials/_edit-history-modal.blade.php` (one `<x-ui.modal>`
per post), `audit-logs/partials/_diff-modal.blade.php` is a **single**
shared `#audit-diff-modal` reused by every row. Each "Ver diff" button
inlines that row's own diff as JSON in `data-old-values`/
`data-new-values` attributes:

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

`resources/js/modules/AuditLogDiffModal.js` reads those attributes on
click, writes them into the shared modal's `[dusk="audit-diff-old"]`/
`[dusk="audit-diff-new"]` `<pre>` blocks, then calls
`window.ModalManager.open('audit-diff-modal')` — this is a JS-only
render step (no AJAX round trip), simplest given a 25-row page. Do not
add a per-row `<x-ui.modal>` for this screen; 25 duplicated modals per
page is exactly the pattern this design avoids.

## `dusk` Selector Contract

| Element | Selector |
| --- | --- |
| Page root | `dusk="audit-logs-index"` |
| Filter form | `dusk="audit-logs-filter-form"` |
| Date/org/event/user filter inputs | `dusk="audit-logs-date-from"`, `-date-to`, `-org-filter`, `-event-filter`, `-user-filter` |
| Results table | `dusk="audit-logs-table"` |
| Each row | `dusk="audit-log-row-{id}"` |
| "Ver diff" button | `dusk="view-diff-{id}"` |
| Diff modal fields | `dusk="audit-diff-event"`, `dusk="audit-diff-old"`, `dusk="audit-diff-new"` |
| CSV export link | `dusk="export-audit-logs-csv"` |
| Sidebar link | `dusk="sidebar-audit-logs-link"` (desktop), `dusk="sidebar-audit-logs-link-mobile"` |
