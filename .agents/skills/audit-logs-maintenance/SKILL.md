---
name: audit-logs-maintenance
description: >
  Debugging, testing, and edge-case guide for the System Audit Logging &
  Monitoring feature (SPEC-15): the mandatory PHPUnit/Dusk test files,
  common `org_id`-null/`UnresolvedOrgContextException`/prune-scope
  failure modes, the retention config, and Dusk gotchas for the diff
  modal. Use when `AuditLogTest` or `AuditLogUiTest` is failing, a guest
  login-failure 500s instead of logging, `audit-logs:prune` deletes the
  wrong (or no) rows, or the "Ver diff" modal doesn't show the expected
  JSON in the browser.
license: MIT
metadata:
  feature: audit-logs
  role: maintenance
  specs:
    - spec/specs/15-system-audit-logging-and-monitoring.md
---

# Audit Logs Maintenance

## Mandatory Test Coverage for This Module

- `tests/Feature/AuditLogTest.php` — covers: `AuditableTrait` firing on
  `created`/`updated`/`deleted` with redaction applied; `AuditService`
  dual-writing DB + the `audit` Monolog channel; the 4 auth listeners
  firing with `password: '[REDACTED]'`; `OrgScope` isolation on
  `index()` (Gestor sees only their own Org, Admin sees/filters all);
  **no** `UnresolvedOrgContextException` for null-org writes (guest
  `login.failed`); every spec §3 critical-action event
  (`csv.import`/`essay.graded`/`certificate.issued`/`.revoked`/
  `impersonate.start`/`.stop`/`content.deleted`/`user.status_changed`)
  recorded with the documented payload shape; `audit-logs:prune`
  deleting only rows older than `retention_days` and bypassing
  `OrgScope`; `index()` filters (date range, event category, user
  search, org) returning correctly scoped results; CSV export streaming
  the full filtered set.
- `tests/Browser/AuditLogUiTest.php` (Dusk) — Admin and Gestor loading
  their respective screens, applying filters, opening the diff modal
  and seeing old/new JSON, paginating, triggering CSV export, and a
  Gestor never seeing another Org's rows or the Org filter dropdown.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=AuditLogTest
vendor/bin/sail dusk --filter=AuditLogUiTest
```

Dusk tests use `DatabaseMigrations`, never `RefreshDatabase` (separate
HTTP process, see `laravel-dusk`/`testing-architecture`).

## Common Failure Modes

- **A guest `login.failed` (or any Admin-global write with no active
  Impersonate Org session) throws `UnresolvedOrgContextException`
  instead of writing `org_id = null`.** This means `AuditLog`'s write
  path is going through `OrgScope`'s default `creating` hook instead of
  bypassing it — see `audit-logs-architecture`'s "Creating-Hook Bypass"
  section. Fix: route every write through
  `AuditLog::withoutEvents(...)` (or an equivalent bypass), never a
  plain `AuditLog::create([...])` call outside that wrapper.
- **`audit-logs:prune` deletes nothing (or deletes only one Org's rows)
  when run from `artisan`/the scheduler.** Confirm the command builds
  its query with `AuditLog::withoutGlobalScopes()` — a bare
  `AuditLog::where('created_at', '<', ...)` still carries `OrgScope`'s
  scope closure, which may add a narrowing (or, in a no-`Auth::user()`
  console context, a no-op) `WHERE` depending on how that closure reads
  `Auth::user()`; either way, un-scoped is the only correct query here
  because pruning is a global retention policy, not a per-tenant one.
- **`old_values`/`new_values` still contain a plaintext password after
  a `User` update.** `AuditObserver` must `unset()` `password`/
  `remember_token` from the arrays built off `getChanges()`/
  `getOriginal()` — a model's `$hidden`/casts do **not** filter what
  those two methods return, so relying on them silently leaks the
  hashed (or, worse, pre-hash-mutator) value into `audit_logs`.
- **A DB failure (or a full disk) on the `audit_logs` INSERT breaks the
  primary request** (e.g. a certificate issuance 500s because the audit
  write threw). `AuditService::log()`'s DB write must be wrapped in its
  own `try`/`catch`; if this guard is missing, an audit-logging bug can
  take down an unrelated feature.
- **A Course/Module/Lesson delete produces two audit rows for the same
  deletion** (one generic `deleted` from `AuditableTrait`, one manual
  `content.deleted` from the controller's `destroy()`). Confirm which
  path is authoritative per model before wiring both — see
  `audit-logs-architecture`'s double-logging note.
- **CSV export only contains the current page's 25 rows.** The export
  action must apply the same filters as `index()` but query/stream
  without `->paginate()` — grep for a stray `->paginate(25)` inside
  `export()`.
- **A Gestor's request can see the Admin-only Org filter dropdown, or a
  spoofed `?org_id=` query string leaks another Org's rows.** The
  `org_id` filter must be server-side ignored for any non-Admin
  request regardless of what the query string contains — see
  `dashboard-conventions`'s equivalent guard on `ReportExportController`.

## Retention Config

`AUDIT_LOG_RETENTION_DAYS` (default `365`, read via
`config('audit.retention_days')`) governs only the `audit_logs` MySQL
table's pruning window — changing it does not affect the separate
`audit` Monolog channel's own log-rotation settings in
`config/logging.php`. When writing a prune test, seed rows straddling
the boundary explicitly (`created_at` at `retention_days - 1`,
`retention_days`, `retention_days + 1` days ago) rather than asserting
on a total count, so an off-by-one in the `<` vs `<=` comparison is
actually caught.

## Dusk Gotchas for the Diff Modal

- The diff modal is a **single shared** `#audit-diff-modal`, not one
  per row (see `audit-logs-conventions`) — a Dusk test must click a
  specific row's `[dusk="view-diff-{id}"]` button and then assert
  against the shared `[dusk="audit-diff-old"]`/`[dusk="audit-diff-new"]`
  content, not look for a per-row modal id.
- `ModalManager`'s backdrop-hide-on-load fix (Alpine.js is not an
  installed dependency, see `ForumEditHistory.js`'s comment) means the
  modal is `display: none` until `AuditLogDiffModal.js` explicitly opens
  it — `waitFor('@audit-diff-old')` alone is not sufficient before the
  click, only after it; assert visibility only after the trigger click,
  and use `waitFor` (never `pause()`) for the post-click content to
  settle, per `laravel-dusk`.
- Because the JSON is written via `.textContent` (not innerHTML), Dusk's
  `assertSee()` against `[dusk="audit-diff-old"]` will match the
  pretty-printed JSON string verbatim (including key names) — assert on
  a distinguishing field value from the seeded `old_values`/`new_values`
  fixture, not the whole blob, to keep the assertion resilient to
  formatting/whitespace differences.

## Open Questions Still Needing a Decision

Logged from the SPEC-15 tech-refine pass — not resolved by this
bucket's implementation:

1. **`csv.import` granularity.** `UserImportService::importChunk()` is
   called once per 50-row browser-side chunk; spec's payload
   (`total_processed`, `file_name`) implies one event per logical
   import, which may require an import-session/finalization step not
   yet designed.
2. **`password.reset` scope.** Whether the event should cover only the
   completed reset (`NewPasswordController`'s existing `PasswordReset`
   event) or also the request stage
   (`PasswordResetLinkController::store()`, which fires no stock
   Illuminate event today).
3. **Exact "Mutação Geral" auditable model list.** Spec §4.1 names 7
   models plus "etc." — confirm whether `InvitationLink`, `ForumTopic`,
   `SystemSetting`, `HelpArticle` are in scope before assuming the list
   is closed.
4. **Event-category → event-name mapping for the RF33 UI dropdown.**
   The 3 category labels (Autenticação / Mutações de Banco / Ações
   Críticas) are named in spec §5 but no exact event-list-per-category
   enum is given — `audit-logs-conventions`'s example 3-key array is a
   working assumption, not a settled contract; confirm before treating
   it as final.
