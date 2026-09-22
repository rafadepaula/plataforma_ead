---
name: tenancy-maintenance
description: >
  Host-based single-database multitenancy: Organizations/`org_id`,
  `ResolveOrgFromHost`, `OrgContext`, `EnsureTenantAccess`, `credentials` table,
  `OrgScope` global scope, Impersonate Org, `RolesEnum`; migration/model/exception
  conventions; cross-tenant leak prevention (DB::table bypasses, unscoped `User`
  queries, cascade-inherited ID guessing, org_id spoofing, Policy dual-verification).
  Use when designing any table/feature that must respect tenant boundaries, creating
  or modifying migrations with `org_id` or tenant-isolated models, writing outside a
  web request (console/queue/factories), handling `UnresolvedOrgContextException`,
  auditing code for tenant isolation/role-based access between Admin, Gestor, Aluno,
  Professor, or when a test leaks data across orgs, the host resolves the wrong
  Organization, or `OrgScopeUnresolvedContextTest`/`HostResolutionTest` fails.
license: MIT
metadata:
  feature: tenancy
  roles: [architecture, conventions, maintenance, security]
---

# Host-based Multitenancy (`tenancy-maintenance`)

Host-based single-database multitenancy: `ResolveOrgFromHost` → `OrgContext` → `OrgScope`/`EnsureTenantAccess`, per-org `credentials` accounts, Impersonate Org, `RolesEnum` — and the security-audit rules that keep tenant isolation from leaking.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | How tenant isolation works, how the request host resolves the Organization, which tables are org-scoped, how per-org accounts/passwords live in `credentials`; before designing a new table/feature that must respect tenant boundaries. |
| `resource/conventions.md` | Creating or modifying a migration with an `org_id` column, a tenant-isolated model, code reading the request-host Organization, writing outside a web request (console/queue/factories), or handling `UnresolvedOrgContextException`. |
| `resource/maintenance.md` | A test leaks data across organizations; host resolves the wrong Organization; Admin-created record misses `org_id`; `OrgScopeUnresolvedContextTest`/`HostResolutionTest` fails; before touching `OrgScope`/`ResolveOrgFromHost`/`EnsureTenantAccess`/`RolesEnum`. |
| `resource/security.md` | Reviewing or writing code to audit single-database tenant isolation, prevent cross-tenant data leaks, and enforce role-based access control between Admin, Gestor, Aluno, and Professor. |
