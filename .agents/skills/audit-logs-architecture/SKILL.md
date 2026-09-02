---
name: audit-logs-architecture
description: >
  System Audit Logging & Monitoring domain: dual storage (MySQL
  `audit_logs` + dedicated `audit` Monolog channel), why `AuditLog` cannot
  reuse `OrgScope`'s `creating` hook as-is (guest/Admin-global writes need
  real `null` `org_id`, no `UnresolvedOrgContextException`),
  `AuditableTrait`/`AuditObserver` mutation interception, event
  taxonomy. Use when designing or reviewing anything that writes
  `audit_logs`, before adding auditable model or critical-action event, or
  when scoping `/admin/audit-logs` / `/gestor/audit-logs`.
license: MIT
metadata:
  feature: audit-logs
  role: architecture
---

# Audit Logs Architecture

## Overview

Audit logging = 3 layers:

1. **Automatic mutation audit** — `AuditableTrait` + `AuditObserver` intercept `created`/`updated`/`deleted` on opted-in models (`Organization`, `User`, `Course`, `Module`, `Lesson`, `Quiz`, `Certificate`). Writes generic `{ModelFQCN}.created`/`.updated`/`.deleted` (e.g. `App\Models\User.updated` — no morph map registered, so `getMorphClass()` returns FQCN, not short alias) with `old_values`/`new_values` diffs.
2. **Critical-action / security audit** — explicit `AuditService::log(...)` call sites for non-Eloquent events: `login.success`, `login.failed`, `logout`, `password.reset`, `impersonate.start`/`.stop`, `user.status_changed`, `csv.import`, `essay.graded`, `certificate.issued`/`.revoked`, `content.deleted`.
3. **Read/query UI** — `AuditLogController` + Blade, gated `role:admin` and `role:gestor`. Two distinct route names/prefixes hitting same controller methods — not one shared `role:admin|gestor` route like `admin.dashboard`. Both URLs are named on purpose.

## Dual Storage: MySQL + Monolog

Every event written to **both**:

- `audit_logs` MySQL table — queryable, paginated, filterable. The read/query UI reads this.
- `audit` Monolog channel (`storage/logs/audit.log`, `config/logging.php`), independent of DB.

Reason = "duplo armazenamento" guarantee: **DB outage or failed `audit_logs` INSERT must never lose the trail**, file copy written independently. `AuditService::log()` attempts both writes. DB failure must not skip or roll back Monolog write, and neither failure may bubble up and break the user-facing request the audit call rides on. Exact try/catch shape: `audit-logs-conventions`.

## `AuditLog` and `OrgScope`: Creating-Hook Bypass

`AuditLog` uses `OrgScope` for its **read side** like `Course`/`InvitationLink` (see `tenancy-architecture`) — Gestor `index()` restricted to own `org_id`, Admin sees everything (or one Org while impersonating).

But `OrgScope::booted()` also registers a `creating` hook that auto-assigns `org_id` from `auth()->user()`/`session('active_org_id')` and **throws `UnresolvedOrgContextException`** when it cannot resolve. Right for `Course`, wrong for `AuditLog`:

- Failed login (`login.failed`) has **no** authenticated user and often no Org. `org_id` must persist as real `NULL`, not throw.
- Admin doing global action (no Impersonate Org session) must also write `org_id = null`.

**Write path bypasses the `creating` hook entirely.** Every `AuditLog` row inserted via path that sets `org_id` explicitly (including `null`) without `OrgScope` auto-assign — `AuditLog::withoutEvents(fn () => AuditLog::create([...]))` or a dedicated `insert()`/`forceCreate()` helper in `AuditService`. **SELECT-side** global scope still applies to `index()` and any other query; only the `creating` auto-resolve-or-throw is skipped. Trickiest edge case in the feature: get it wrong and either every guest login-failure 500s, or every Admin-global write mis-assigns an `org_id`.

`audit-logs:prune` has the mirror concern on **read** side: run `AuditLog::withoutGlobalScopes()` before deleting old rows, else it prunes only whatever Org context the process resolves. In Artisan/Scheduler with no authenticated user, `OrgScope`'s closure typically returns early adding no `WHERE` — verify with a test, do not assume. Accidentally narrower scope under-prunes silently, no error.

## `AuditableTrait` / `AuditObserver`

`AuditableTrait::bootAuditableTrait()` calls `static::observe(AuditObserver::class)`. Any model with `use AuditableTrait;` gets `created`/`updated`/`deleted` observed free — same lifecycle-hook style as `OrgScope`, no call-site plumbing. `AuditObserver`:

1. Builds `new_values` from `$model->getChanges()` (on `updated`) or full attribute set (on `created`); `old_values` from `$model->getOriginal()` (on `updated`/`deleted`).
2. **Redacts** `password`/`remember_token` from both arrays before persisting — required even for cast/hidden attributes, since `getChanges()`/`getOriginal()` expose them regardless of `$hidden`/casts. Exact call: `audit-logs-conventions`.
3. Delegates to `AuditService::log()` with `event` = `$model->getMorphClass().'.'.$action`, `auditable_type`/`auditable_id` from model, `org_id`/`user_id` from model's own `org_id` (if present) and `auth()->id()`.

**No double-audit on delete.** Model with manual `content.deleted` in its `destroy()` (Course, Module, Lesson — the "Gestão Conteúdo" category) should generally not also carry generic `AuditableTrait` `deleted` handling, else one logical delete makes two rows under two event names for same `auditable_id`. Pick the authoritative path per model before wiring both.

## Event Taxonomy

| Category | Events | Trigger |
| --- | --- | --- |
| Autenticação | `login.success`, `login.failed`, `logout`, `password.reset` | Auth event listeners |
| Gestão de Acesso | `impersonate.start`, `impersonate.stop` | `ImpersonateOrgController` |
| Gestão de Usuários | `user.status_changed` | `UserController::update()` |
| Importação em Lote | `csv.import` | `UserImportService` |
| Avaliações | `essay.graded` | `GradeEssayAnswerAction` |
| Certificados | `certificate.issued`, `certificate.revoked` | `IssueCertificateAction`, `RevokeCertificateAction` |
| Gestão de Conteúdo | `content.deleted` | Course/Module/Lesson `destroy()` |
| Mutação Geral | `{ModelFQCN}.created`, `.updated`, `.deleted` | `AuditableTrait`/`AuditObserver` |

`login.failed` and any guest event **must** allow `org_id` and `user_id` both `null`. Never guess Org from unverified `email` string.

## Config

`config/audit.php` `retention_days` (default 365, `env('AUDIT_LOG_RETENTION_DAYS')`) governs only the `audit-logs:prune` DB window. No effect on `audit` Monolog channel file rotation in `config/logging.php` — independent by design: DB copy obeys the `retention_days` window, file copy is the duplo-armazenamento fallback and may retain longer or shorter.
