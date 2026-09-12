---
name: tenancy-architecture
description: >
  Host-based Single-Database multitenancy architecture of Plataforma EAD
  (Organizations / `org_id`, `ResolveOrgFromHost`, `OrgContext`,
  `EnsureTenantAccess`, `credentials` table, OrgScope global scope,
  Impersonate Org, RolesEnum). Use when you need how tenant isolation
  works, how the request host resolves the Organization, which tables are
  org-scoped, how per-org accounts/passwords live in `credentials`, or
  before designing new table/feature that must respect tenant boundaries.
license: MIT
metadata:
  feature: tenancy
  role: architecture
---

# Tenancy Architecture

## Overview

Platform is **multitenant on single MySQL/MariaDB database** (chosen for
shared-hosting compatibility — no per-tenant database, no per-tenant
process). Each tenant is an **Organization** (`organizations` table,
unique `host` column). Domain tables carry `org_id` foreign key.
Isolation enforced application-side by `OrgScope` Eloquent trait and
`EnsureTenantAccess` middleware, not database row-level security.

Tenant resolution is **by request host**, not by session, domain
convention or package: `App\Http\Middleware\ResolveOrgFromHost` reads
the raw `Host` header (lowercased, port stripped; NOT
`Request::getHost()`, which prefers `SERVER_NAME` and misses the host
the browser asked for), matches it exactly against `organizations.host`
and binds the request's `App\Services\OrgContext` singleton. The
middleware chain (registered in `bootstrap/app.php`):

1. `ResolveOrgFromHost` — **first** middleware of the `web` group
   (`prependToGroup`), so every downstream layer reads a bound context.
   Soft-deleted Organizations never resolve (their host is state zero).
   Performs no redirecting.
2. Session/auth middleware (framework defaults).
3. `EnsureTenantAccess` — **appended** to the `web` group; does the
   gating below.

## `OrgContext` — the Per-Request Tenant State

`App\Services\OrgContext` is an immutable value object bound as a
container singleton per request (`organization`, `orgIsActive`).
`OrgContext::current()` is safe outside the web middleware (console,
queue, early container access): anything before `ResolveOrgFromHost`
runs is **state zero** (`organization = null`, `orgIsActive = false`) by
definition. Helpers: `isStateZero()`, `isBound()` (true only when a real
bind happened — used by `OrgScope`'s creating hook), `orgId()`
(`null` = global Admin credential target). The context is never
persisted to the session; the session only carries the Admin's
`active_org_id` impersonation.

## Access Gating (`EnsureTenantAccess`)

Two states get special treatment; the third — matched, active
Organization — passes straight through:

- **State zero** (host matched nothing): guest is redirected to the
  login (auth routes `login`, `logout`, `forgot-password`, `reset-password`
  stay reachable); authenticated non-Admin is logged out with the generic
  `auth.failed`; authenticated Admin navigates freely (Organization CRUD
  lives here).
- **Inactive Organization**: only public surfaces stay reachable — the
  landing, the certificate lookup (`validar-certificado`) and the auth
  routes (their POSTs must reach the provider to fail with the generic
  error — no distinguishable "disabled tenant" message). Guests asking
  for anything else are bounced to `/`; authenticated non-Admin is logged
  out **with session invalidation**.
- **Active Organization**: authenticated non-Admin must hold an **active**
  credential on THIS portal (`User::credentialFor($context->organization)`,
  `status !== 'active'` → logout + invalidate). This is the per-request
  **kill switch**: a session issued by another portal (cross-host) or left
  over from before the account was deactivated dies on the next request.
  Admins bypass (they act globally or via impersonation).

## Identity Model: `users` (person) + `credentials` (per-org account)

`users` is the **person identity** only: `name`, `email` (unique),
`cpf` (unique). It has **no** `password`, `status`, `remember_token` or
`org_id` columns anymore.

`credentials` (migration `2026_08_01_000003_create_credentials_table.php`)
is the per-Organization account behind a person:

- `user_id` FK → `users` `cascadeOnDelete()`;
- `org_id` FK → `organizations` **nullable** `restrictOnDelete()`;
  `org_id = null` is the **global Admin credential** — the only account
  valid in state zero (the only one that logs in on an unmapped host), and
  it ALSO validates on every Organization portal (fallback after the host
  account in `OrgCredentialUserProvider::sessionCredential()`);
- `password` (hashed cast), `status` enum `active`/`inactive`,
  `remember_token`;
- `unique(user_id, org_id)` — one account per (person, org) pair. MySQL
  permits multiple NULL `org_id` rows, so the single-Admin-credential rule
  is enforced application-side.

Login identity = the `(user, org)` pair where org comes from the request
host. The same e-mail says nothing about which portals a person belongs
to; a person missing an account on portal B fails there exactly like a
wrong password (`auth-orgs-architecture`).

## The Four Roles (`RolesEnum`)

Defined in `App\Enums\Permissions\RolesEnum` (backed string enum:
`admin`, `gestor`, `aluno`, `professor`), enforced via
`spatie/laravel-permission` roles (roles are **global** —
`config('permission.teams')` stays `false`; org partitioning done
exclusively through `org_id` + `OrgScope`, never through Spatie team IDs).

| Role | Accounts (`credentials`) | Scope of data access |
| --- | --- | --- |
| `admin` | one global credential (`org_id = null`) | Global by default. Can narrow to one Organization via **Impersonate Org** (`session('active_org_id')`), which overrides the host for scoping AND writes. |
| `gestor` | one credential per portal they manage on | Everything under `OrgScope` is automatically filtered to the host Organization (`OrgContext::current()->orgId()`). |
| `aluno` | one credential per portal they belong to | Enrolls in courses across multiple Organizations via `course_user`. Course/classroom context resolves the Org from `courses.org_id`, not from the student's row. |
| `professor` | one credential per portal they teach on | Teaches courses assigned via the `course_professor` pivot (`User::teaches()`); manages content/grading only on assigned courses. Lands on `professor.dashboard` (`UserHomeResolver::resolve()`); gated by `role:professor` middleware. |

**Never** apply `OrgScope` to `User` or `Credential`. People must stay
queryable across organizations (login lookup by e-mail is global; admin
user management lists all portals); `Credential` gets its org target from
the host context explicitly on every query (`Credential::scopeForOrg()`).

## Data Model — Org-Scoped vs Cascade-Inherited Tables

**Directly org-scoped** (own `org_id` column, `OrgScope` trait applied —
exactly five models: `Course`, `StudentInvitation`, `ForumTopic`,
`HelpArticle`, `AuditLog`). `help_articles.org_id` nullable (global or
org-specific); `audit_logs.org_id` nullable (guest/Admin-global events
legitimately have `null`, see `audit-logs-architecture`).
`system_settings` is directly org-scoped by column but does **not** use
the `OrgScope` trait: its `org_id` is non-nullable with `default(0)`
sentinel `SystemSetting::GLOBAL_ORG_ID = 0` and a composite PK
`(setting_key, org_id)` (see migration `2026_08_01_000021`) — global
settings are real `org_id = 0` rows, not `NULL`, so lookups resolve the
sentinel through `forOrg()` instead of any global scope.

**Cascade-inherited** (no own `org_id`; org implied by parent FK,
`OrgScope` not applied — scope through parent relation instead; policy is
the enforcement point): `modules` → `courses.org_id`, `lessons` →
`modules` → `courses.org_id`, `quizzes`/`quiz_questions`/`quiz_options`
→ `lessons` → ... → `courses.org_id`, `certificates` →
`course_id`/`user_id`, `course_user` (pivot — intentionally NOT
org-scoped, since it is how a student enrolls across multiple orgs),
`forum_replies` → `forum_topics.org_id`, `credentials` → org comes from
the host context, never a global scope.

**Never org-scoped**: `users` and `credentials` (see above),
`notifications` (polymorphic `notifiable`, org implied by notifiable
user).

**Pseudo-polymorphic, no `org_id`, no FK at all** (integrity validated at
application layer only — not to be confused with cascade-inherited tables
above, which do have real parent FK): `forum_post_edits` and
`forum_reports` both carry `postable_type`/`postable_id`
pointing at `ForumTopic`/`ForumReply` written as model FQCN, with no
database foreign key on the pair; resolved exclusively via
`$type::withTrashed()->find($id)` (see `forum-architecture`).
`course_completion_rules.target_id` is same pattern one column
deep, pointing at `modules.id`/`quizzes.id` depending on `rule_type` (see
`certificates-architecture`).

This skill does not repeat column types, only tenancy shape.

## `OrgScope` Trait — How It Behaves (`app/Models/Traits/OrgScope.php`)

Two responsibilities:

1. **Global scope (`bootOrgScope`)** — no-op when nobody is
   authenticated (public routes like certificate validation and
   invitation redemption query unscoped by design).
   - `admin` role: filters by `session('active_org_id')` only if
     Impersonate Org active; otherwise sees all organizations.
   - Any other authenticated user: filtered to the host Organization —
     `OrgContext::current()->orgId() ?? 0`. Org `0` never exists, so an
     authenticated non-Admin on an unmapped host reads an empty set — a
     safety fallback, not a feature.

2. **`org_id` auto-assignment (`booted`/`creating`)** — always
   overwrites `org_id` with the server-resolved context, never trusting
   mass-assigned request input: Admin → `session('active_org_id')`;
   everyone else → `OrgContext::current()->orgId()`. When neither
   resolves: inside a real request the host always resolved first, so a
   write attempt must fail with `UnresolvedOrgContextException` — except
   when `OrgContext::isBound()` is `false` (console, queue, test
   factories, no request in flight): there the context is not
   authoritative and an **explicitly given** `org_id` stands (see the
   docblock in `app/Models/Traits/OrgScope.php`). The exception is
   mapped globally (see `tenancy-conventions`): JSON/AJAX callers get
   HTTP 422, web callers get a redirect-back (302) with a flashed error
   message (`bootstrap/app.php`).

## Impersonate Org

Admin (global credential) can temporarily scope own session to one
Organization by setting `session(['active_org_id' => $orgId])`. Plain
session flag, not package feature — no `Tenant::makeCurrent()` call.
For an Admin it **precedes** the request host as write/scoping context
(`tests/Feature/Tenancy/AdminImpersonationPrecedenceTest.php` pins this).
Clearing the session key (or logging out — `AuthenticatedSessionController::destroy()`
forgets it explicitly) returns Admin to global, unscoped view.

## Account Kill Switches

Two layers prevent a stale session/cookie from outliving a deactivation:

- **Per request**: `EnsureTenantAccess` re-validates credential status on
  the host org for every non-Admin request (see gating above).
- **On mutation**: every password change and every deactivation rotates
  the remember token via `Credential::rotateRememberToken()`, killing the
  "remembered" device (the cookie is only validated against the stored
  value). The global Admin screen's `admin.users.status` flip and
  password set iterate all of the person's credentials doing exactly this
  (`UserAdminController`).

## Related

- `auth-orgs-architecture` — provider, remember-me, password reset on the
  `credentials` model.
- `skill-autoupdate` — the auto-update protocol these skills are subject
  to.
