---
name: audit-logs-architecture
description: >
  Explains the System Audit Logging & Monitoring domain (SPEC-15): the
  dual-storage design (MySQL `audit_logs` + a dedicated `audit` Monolog
  channel), why `AuditLog` cannot reuse `OrgScope`'s `creating` hook
  as-is (guest/Admin-global writes need a genuinely `null` `org_id`
  without throwing `UnresolvedOrgContextException`), the
  `AuditableTrait`/`AuditObserver` automatic-mutation-interception model,
  and the event taxonomy from spec §3. Use whenever designing or
  reviewing a feature that writes to `audit_logs`, before adding a new
  auditable model or a new critical-action event, or when deciding how
  the `/admin/audit-logs`/`/gestor/audit-logs` screens should be scoped.
license: MIT
metadata:
  feature: audit-logs
  role: architecture
  specs:
    - spec/specs/15-system-audit-logging-and-monitoring.md
---

# Audit Logs Architecture

## Overview

SPEC-15 has 3 layers:

1. **Automatic mutation auditing** (RF31) — `AuditableTrait` +
   `AuditObserver` intercept `created`/`updated`/`deleted` on opted-in
   models (`Organization`, `User`, `Course`, `Module`, `Lesson`, `Quiz`,
   `Certificate`, per spec §4.1) and write a generic
   `{ModelFQCN}.created`/`.updated`/`.deleted` event (e.g.
   `App\Models\User.updated` — no morph map is registered, so
   `getMorphClass()` returns the fully-qualified class name, not a short
   alias) with `old_values`/`new_values` diffs.
2. **Critical-action/security auditing** (RF32) — explicit
   `AuditService::log(...)` call sites for events that are not a simple
   Eloquent mutation: auth events (`login.success`, `login.failed`,
   `logout`, `password.reset`), `impersonate.start`/`.stop`,
   `user.status_changed`, `csv.import`, `essay.graded`,
   `certificate.issued`/`.revoked`, `content.deleted`.
3. **The `/admin/audit-logs` / `/gestor/audit-logs` read/query UI**
   (RF33) — `AuditLogController` + Blade views, gated `role:admin` and
   `role:gestor` respectively (two distinct route names/prefixes
   pointing at the same controller methods, not one shared
   `role:admin|gestor` route the way `admin.dashboard` is — SPEC-15 §1
   deliberately calls out both URLs).

## Dual Storage: MySQL + Monolog

Every audit event is written to **both**:

- The `audit_logs` MySQL table (queryable, paginated, filterable — what
  the RF33 UI reads).
- A dedicated `audit` Monolog channel (`storage/logs/audit.log`,
  `config/logging.php`), independent of the DB.

This is not redundant logging for its own sake — it is RN's "duplo
armazenamento" guarantee: **a database outage or a failed `audit_logs`
INSERT must never lose the audit trail**, because the file-based copy is
written independently. `AuditService::log()` must therefore attempt both
writes and must not let a DB failure prevent (or roll back) the Monolog
write, nor let either failure bubble up and break the primary
user-facing request the audit call is piggy-backing on (see
`audit-logs-conventions` for the exact try/catch shape).

## `AuditLog` and `OrgScope`: the Creating-Hook Bypass

`AuditLog` uses the `OrgScope` trait for its **read side** exactly like
`Course`/`InvitationLink` (see `tenancy-architecture`) — a Gestor's
`index()` query is automatically restricted to their own `org_id`, an
Admin sees everything (or one Org while impersonating).

But `OrgScope::booted()` also registers a `creating` Eloquent hook that
auto-assigns `org_id` from `auth()->user()`/`session('active_org_id')`
and **throws `UnresolvedOrgContextException`** when it cannot resolve
one. That behavior is correct for `Course` (a Course always belongs to
exactly one Org) but is actively wrong for `AuditLog`:

- A failed login (`login.failed`) has **no** authenticated user and
  often no identifiable Org at all — `org_id` must be allowed to persist
  as a genuine `NULL`, not throw.
- An Admin performing a truly global action (no active Impersonate Org
  session) must also be able to write an audit row with `org_id = null`.

**The write path must bypass the `creating` hook entirely** — every
`AuditLog` row is inserted via a path that sets `org_id` explicitly
(including `null`) without going through `OrgScope`'s auto-assign
logic, e.g. `AuditLog::withoutEvents(fn () => AuditLog::create([...]))`
or a dedicated `insert()`/`forceCreate()`-style helper in
`AuditService`. The **SELECT-side** global scope (Gestor sees only their
own `org_id`) still applies normally to `index()`/any other query —
only the `creating` hook's auto-resolve-or-throw behavior is skipped.
This is the single trickiest edge case in the whole feature; get it
wrong and either every guest login-failure attempt 500s, or every
Admin-global write silently mis-assigns an `org_id`.

The `audit-logs:prune` command has the mirror-image concern on the
**read** side: it must run `AuditLog::withoutGlobalScopes()` before
deleting old rows, or it will only ever prune whatever Org context the
executing process happens to resolve (in an Artisan/Scheduler context
with no authenticated user, `OrgScope`'s scope closure typically
returns early with no `WHERE` added at all — verify this explicitly with
a test rather than assuming it, since a query with an accidentally
narrower scope will silently under-prune, not error).

## `AuditableTrait` / `AuditObserver` Interception Model

`AuditableTrait::bootAuditableTrait()` calls
`static::observe(AuditObserver::class)` — any model that
`use AuditableTrait;` gets `created`/`updated`/`deleted` observed for
free, mirroring how other traits in this codebase (`OrgScope`) hook
Eloquent's model-event lifecycle rather than requiring call-site
plumbing. `AuditObserver`:

1. Builds `new_values` from `$model->getChanges()` (on `updated`) or the
   full attribute set (on `created`), and `old_values` from
   `$model->getOriginal()` (on `updated`/`deleted`).
2. **Redacts** `password`/`remember_token` from both arrays before
   anything is persisted — this must happen even when those attributes
   are cast/hidden, because Eloquent's `getChanges()`/`getOriginal()`
   still expose them regardless of `$hidden`/casts (see
   `audit-logs-conventions` for the exact redaction call).
3. Delegates to `AuditService::log()` with `event` built as
   `$model->getMorphClass().'.'.$action` (`$action` being
   `created`/`updated`/`deleted`), `auditable_type`/`auditable_id` set
   from the model, and `org_id`/`user_id` resolved from the model's own
   `org_id` (if present) and `auth()->id()`.

**Do not** double-audit a deletion. A model that gets a manual
`content.deleted` call at its controller's `destroy()` (Course, Module,
Lesson — spec §3's "Gestão Conteúdo" row) should generally **not** also
carry generic `AuditableTrait` `deleted` handling for the same mutation,
or a single logical delete produces two audit rows under two different
event names for the same `auditable_id`. Confirm which of the two paths
(generic `deleted` event vs. manual `content.deleted`) is authoritative
for each model before wiring both.

## Event Taxonomy (spec §3)

| Category | Events | Trigger |
| --- | --- | --- |
| Autenticação | `login.success`, `login.failed`, `logout`, `password.reset` | Auth event listeners |
| Gestão de Acesso | `impersonate.start`, `impersonate.stop` | `ImpersonateOrgController` |
| Gestão de Usuários | `user.status_changed` | `UserController::update()` |
| Importação em Lote | `csv.import` | `UserImportService` |
| Avaliações | `essay.graded` | `GradeEssayAnswerAction` |
| Certificados | `certificate.issued`, `certificate.revoked` | `IssueCertificateAction`, `RevokeCertificateAction` |
| Gestão de Conteúdo | `content.deleted` | Course/Module/Lesson `destroy()` |
| Mutação Geral | `{ModelFQCN}.created`, `.updated`, `.deleted` (e.g. `App\Models\User.created`) | `AuditableTrait`/`AuditObserver` |

`login.failed` and any other guest/unauthenticated event **must** allow
`org_id`/`user_id` to both be `null` — never attempt to guess an Org
from an unverified `email` string.

## Config

`config/audit.php`'s `retention_days` (default 365, `env('AUDIT_LOG_RETENTION_DAYS')`)
governs the `audit-logs:prune` DB deletion window only — it has no
effect on the separate `audit` Monolog channel's own file-rotation
settings in `config/logging.php` (those are independent by design: the
DB copy is what RF33's retention policy governs, the file copy is the
duplo-armazenamento fallback and can retain longer or shorter
independently).
