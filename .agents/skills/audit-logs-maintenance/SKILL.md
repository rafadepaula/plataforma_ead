---
name: audit-logs-maintenance
description: >
  Debug, test, edge-case guide for System Audit Logging & Monitoring
  (SPEC-15): mandatory PHPUnit/Dusk files, `org_id`-null /
  `UnresolvedOrgContextException` / prune-scope failure modes, retention
  config, Dusk gotchas for diff modal. Use when `AuditLogTest` or
  `AuditLogUiTest` fails, guest login-failure 500s instead of logging,
  `audit-logs:prune` deletes wrong or no rows, or "Ver diff" modal shows
  no JSON.
license: MIT
metadata:
  feature: audit-logs
  role: maintenance
  specs:
    - spec/specs/15-system-audit-logging-and-monitoring.md
---

# Audit Logs Maintenance

## Mandatory Test Coverage

- `tests/Feature/AuditLogTest.php` — `AuditableTrait` firing on `created`/`updated`/`deleted` with redaction; `AuditService` dual-writing DB + `audit` Monolog channel; 4 auth listeners firing with `password: '[REDACTED]'`; `OrgScope` isolation on `index()` (Gestor own Org only, Admin all); **no** `UnresolvedOrgContextException` on null-org write (guest `login.failed`); every spec §3 critical-action event (`csv.import`, `essay.graded`, `certificate.issued`/`.revoked`, `impersonate.start`/`.stop`, `content.deleted`, `user.status_changed`) with documented payload; `audit-logs:prune` deleting only rows older than `retention_days` and bypassing `OrgScope`; `index()` filters (date range, event category, user search, org) scoped right; CSV export streaming full filtered set.
- `tests/Browser/AuditLogUiTest.php` (Dusk) — Admin and Gestor loading their screens, filtering, opening diff modal and seeing old/new JSON, paginating, CSV export, Gestor never seeing another Org's rows or the Org dropdown.

Run narrowest first:

```bash
vendor/bin/sail artisan test --filter=AuditLogTest
vendor/bin/sail dusk --filter=AuditLogUiTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from `Tests\DuskTestCase`. `RefreshDatabase` forbidden (Dusk runs separate HTTP process). `DatabaseMigrations` retired (per-method `migrate:fresh`). See `laravel-dusk`/`testing-conventions`.

## Failure Modes

- **Guest `login.failed` (or Admin-global write with no Impersonate Org) throws `UnresolvedOrgContextException` instead of writing `org_id = null`.** Write path went through `OrgScope`'s `creating` hook — see "Creating-Hook Bypass" in `audit-logs-architecture`. Fix: route every write through `AuditLog::withoutEvents(...)` or equivalent bypass. Never plain `AuditLog::create([...])` outside that wrapper.
- **`audit-logs:prune` deletes nothing, or only one Org's rows, from artisan/scheduler.** Query must use `AuditLog::withoutGlobalScopes()`. Bare `AuditLog::where('created_at', '<', ...)` still carries `OrgScope`'s closure, which may add a narrowing (or, without `Auth::user()`, a no-op) `WHERE`. Pruning = global retention policy, so unscoped is the only correct query.
- **`old_values`/`new_values` still hold plaintext password after `User` update.** `AuditObserver` must `unset()` `password`/`remember_token` from arrays built off `getChanges()`/`getOriginal()`. `$hidden`/casts do **not** filter those two methods — relying on them leaks the hashed (or pre-hash-mutator) value into `audit_logs`.
- **DB failure or full disk on `audit_logs` INSERT breaks the primary request** (certificate issuance 500s because audit write threw). `AuditService::log()`'s DB write needs its own `try`/`catch`. Missing guard = audit bug takes down unrelated feature.
- **Course/Module/Lesson delete makes two audit rows** (generic `deleted` from `AuditableTrait` + manual `content.deleted` from `destroy()`). Pick authoritative path per model — see double-logging note in `audit-logs-architecture`.
- **CSV export only has current page's 25 rows.** Export must apply same filters as `index()` but stream without `->paginate()`. Grep for stray `->paginate(25)` inside `export()`.
- **Gestor sees Admin-only Org dropdown, or spoofed `?org_id=` leaks another Org.** `org_id` filter must be ignored server-side for any non-Admin request, whatever the query string says. Same guard as `ReportExportController` in `dashboard-conventions`.

## Retention Config

`AUDIT_LOG_RETENTION_DAYS` (default `365`, via `config('audit.retention_days')`) governs only `audit_logs` MySQL pruning window. No effect on `audit` Monolog channel rotation in `config/logging.php`. In a prune test, seed rows straddling the boundary (`created_at` at `retention_days - 1`, `retention_days`, `retention_days + 1` days ago) instead of asserting a total count — catches off-by-one between `<` and `<=`.

## Dusk Gotchas: Diff Modal

- Modal is **single shared** `#audit-diff-modal`, not one per row (`audit-logs-conventions`). Test clicks a row's `[dusk="view-diff-{id}"]`, then asserts against shared `[dusk="audit-diff-old"]`/`[dusk="audit-diff-new"]`. No per-row modal id exists.
- The diff modal is a `bootstrap.Modal` (no Alpine.js, no `ModalManager` — both retired in the Bootstrap 5.3 migration): it stays hidden until the trigger opens it and `AuditLogDiffModal.js` fills the two panes. `waitFor('@audit-diff-old')` before the click is not enough — assert visibility only after the trigger click, and use `waitFor` (never `pause()`) for content, per `laravel-dusk`.
- JSON written via `.textContent`, so `assertSee()` on `[dusk="audit-diff-old"]` matches pretty-printed JSON verbatim including key names. Assert one distinguishing field value from the fixture, not the whole blob — resilient to whitespace/format changes.

## Open Questions

From SPEC-15 tech-refine. Not resolved by this bucket:

1. **`csv.import` granularity.** `UserImportService::importChunk()` runs once per 50-row browser chunk. Spec payload (`total_processed`, `file_name`) implies one event per logical import — needs an import-session/finalization step not yet designed.
2. **`password.reset` scope.** Only completed reset (`NewPasswordController`'s `PasswordReset` event), or also request stage (`PasswordResetLinkController::store()`, which fires no stock Illuminate event today)?
3. **Exact "Mutação Geral" model list.** Spec §4.1 names 7 models plus "etc.". Confirm whether `InvitationLink`, `ForumTopic`, `SystemSetting`, `HelpArticle` are in scope before treating list as closed.
4. **Event-category to event-name mapping for RF33 dropdown.** 3 labels named in spec §5, no per-category enum given. The 3-key array in `audit-logs-conventions` is a working assumption, not settled.

---

## E2E Coverage Lives in Lifecycle Chains

`tests/Browser/` groups by **user journey (lifecycle chain)** — one method drives create, edit, state change, delete, consequence. Not by module, spec, or use case.

- **Find coverage**: chain methods may sit in a file named after another module when the journey crosses boundaries. Search `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name. Missing per-module file is **not** a gap.
- **Add coverage**: extend the journey's chain with a numbered step carrying UI **and** DB assertion. New method only for independent negatives (403, cross-tenant, other actor). New file only for genuinely new journey.
- **Debug**: stack trace points at a step — match line to its `// N.` comment. Late failure usually means earlier step did not persist.
- **Database**: no DB trait in `tests/Browser/*`; `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` = suite-wide slowdown. Files, cache, session not reset between methods.

Full rule: `testing-conventions`. Chain debug: `testing-maintenance`.
