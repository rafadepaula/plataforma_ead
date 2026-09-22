---
name: auth-orgs-maintenance
description: >
  Host-scoped auth (per-org `credentials` accounts, `org-credential` user provider,
  remember-me per credential, role-based redirect, host-aware password-reset token)
  plus Organization/User CRUD, `ProvisionOrgAccountAction`, CSV import, Policies, Form
  Requests and guest-shell auth views. Use when touching auth routes/controllers/views
  or code reading `Auth::user()`/`session('active_org_id')` at login/logout, or when
  `HostScopedLoginTest`, `MultiTenantStudentImportTest` or `UserCrudTest` fails, login
  passes on the wrong host, remember-me/throttle misbehaves per org, or an imported
  student misses enrollment or duplicates a User row.
license: MIT
metadata:
  feature: auth-orgs
  roles: [architecture, conventions, maintenance]
---

# Auth & Organizations (`auth-orgs-maintenance`)

Host-scoped authentication and Organizations/Users management: per-org `credentials` accounts, `org-credential` user provider, remember-me per credential, role-based redirect, host-aware password reset, Organization/User CRUD, account provisioning and chunked CSV import.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | How a user authenticates on a portal host, why `status=active` credential gates login, how remember-me validates per organization, how post-login redirect resolves per role — before touching auth routes/controllers/views or code reading `Auth::user()`/`session('active_org_id')` at login/logout. |
| `resource/conventions.md` | Writing a controller, Policy, or Form Request managing `Organization`/`User`, provisioning accounts via `ProvisionOrgAccountAction`, touching the login throttle key or generic auth messages, handling upload to the `public` disk, wiring an admin-only/gestor-only route, or editing the guest-shell auth views. |
| `resource/maintenance.md` | `MultiTenantStudentImportTest` or `UserCrudTest` fails; a login fails on the right host (or passes on the wrong one); remember-me or throttle misbehaves per org; an imported student misses enrollment or duplicates a User row; `UnresolvedOrgContextException` fires during import. |
