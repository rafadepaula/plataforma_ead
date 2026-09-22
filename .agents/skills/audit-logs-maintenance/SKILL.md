---
name: audit-logs-maintenance
description: >
  System Audit Logging & Monitoring: dual storage (MySQL `audit_logs` + `audit` Monolog
  channel), `AuditableTrait`/`AuditObserver`, `AuditService::log()` call-site pattern,
  redaction, `admin.audit-logs.index`/`.export` route contract, diff modal, CSV export
  streaming, prune/retention, failure modes and Dusk gotchas. Use when designing,
  writing or reviewing anything that writes `audit_logs`, before adding an auditable
  model or critical-action event, when `AuditLogTest` or `AuditLogUiTest` fails, guest
  login-failure 500s instead of logging, `audit-logs:prune` deletes wrong or no rows,
  or "Ver diff" modal shows no JSON.
license: MIT
metadata:
  feature: audit-logs
  roles: [architecture, conventions, maintenance]
---

# Audit Logs (`audit-logs-maintenance`)

System Audit Logging & Monitoring: dual storage (MySQL `audit_logs` + dedicated `audit` Monolog channel), `AuditableTrait`/`AuditObserver` mutation interception, `AuditService::log()` call-site pattern with redaction, the Admin-only `/admin/audit-logs` screen, and the prune/retention pipeline.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing anything that writes `audit_logs`, before adding an auditable model or critical-action event, or when scoping `/admin/audit-logs`. |
| `resource/conventions.md` | Writing controller, service, observer, listener, Blade view, or JS that touches `AuditLog` rows or the `/admin/audit-logs` screen. |
| `resource/maintenance.md` | `AuditLogTest`/`AuditLogUiTest` fails; guest login-failure 500s instead of logging; `audit-logs:prune` deletes wrong or no rows; "Ver diff" modal shows no JSON. |
