---
name: tenancy-maintenance
description: >
  Debug, test, edge-case guide for host-based multitenancy module (host
  resolution, org isolation, per-org credentials, Impersonate Org,
  UnresolvedOrgContextException). Use when test leak data across
  organizations, host resolves wrong Organization, Admin-created record
  miss org_id, OrgScopeUnresolvedContextTest or HostResolutionTest or
  tenant-isolation test fail, or before touch OrgScope/ResolveOrgFromHost/
  EnsureTenantAccess/RolesEnum/org-scoped migrations and need know what
  else must change with it.
license: MIT
metadata:
  feature: tenancy
  role: maintenance
---

# Tenancy Maintenance

## Mandatory Test Coverage for This Module

These tests guard tenancy contract. Must stay green (PHPUnit, per project
convention — no Pest):

- `tests/Feature/Tenancy/HostResolutionTest.php` — THE reference suite for
  host tenancy: raw `Host` header match (case-insensitive, port stripped),
  unknown host/org-without-host/soft-deleted org all resolve to state
  zero, plus the `EnsureTenantAccess` gating (state-zero guest → login;
  non-Admin logged out in state zero; inactive org serves only
  landing/auth/certificate lookup and logs non-Admin out; non-Admin
  without an ACTIVE credential on the host portal — cross-host session or
  deactivated account — is logged out; Admin navigates freely in state
  zero).
- `tests/Feature/Tenancy/AdminImpersonationPrecedenceTest.php` — Admin
  impersonating an org of another host still writes into the impersonated
  org; Admin without impersonation never inherits the host org on create.
- `tests/Feature/OrgScope/OrgScopeUnresolvedContextTest.php` — assert
  `UnresolvedOrgContextException` thrown (JSON/AJAX mapped to HTTP 422,
  web mapped to redirect-back 302 + flash) when Admin
  with no Impersonate Org active create org-scoped record.
- `tests/Feature/OrgScope/OrgScopeTenantIsolationTest.php` — assert Gestor of
  Org A cannot see/query records of Org B through org-scoped model.
- `tests/Feature/OrgScope/OrgScopeImpersonateOrgTest.php` — assert Admin with
  `session('active_org_id')` set see only that org records, and Admin without it
  set see across all orgs.
- `tests/Feature/Auth/RolesMiddlewareTest.php` — `role:admin` / `role:gestor` /
  `role:aluno` / `role:professor` gate checks.
- `tests/Unit/Enums/RolesEnumTest.php` — `RolesEnum` values/labels.

Run narrowest first after touching `OrgScope`, `ResolveOrgFromHost`,
`EnsureTenantAccess`, `RolesEnum`, or any org-scoped migration:

```bash
vendor/bin/sail artisan test --compact tests/Feature/Tenancy/HostResolutionTest.php
vendor/bin/sail artisan test --filter=OrgScope
vendor/bin/sail artisan test --compact tests/Feature/Auth/RolesMiddlewareTest.php
```

## Diagnosing "Data Leaking Across Organizations"

1. Confirm model really `use`s `OrgScope`. Cascade-inherited models (`Module`,
   `Lesson`, `Quiz`, `Credential`, ...) carry no trait by design; query built
   direct against them without joining/scoping through parent (`$course->modules()`)
   or without an explicit `Credential::forOrg($orgId)` is not tenant-filtered.
   Expected, not bug.
2. Confirm caller authenticated. `OrgScope` global scope is no-op when
   `Auth::user()` null (by design, for public routes like certificate
   validation). Background job or console command running without authenticated
   user see unfiltered data unless it manually scope query.
3. For Admin, check whether `session('active_org_id')` really set. Admin with no
   active Impersonate Org see *all* organizations by design — not leak,
   documented "global view" behavior.

## Diagnosing "Record Created With Wrong or Missing `org_id`"

- Silently got `org_id = null` on org-scoped table: regression. Current trait
  must throw `UnresolvedOrgContextException` (inside a request) or honor the
  explicit `org_id` (console/queue/factories, `OrgContext::isBound() === false`).
  Check `OrgScope::booted()` `creating` hook not bypassed (via `forceCreate()`,
  `insert()`, or mass-insert query builder call skipping Eloquent events
  entirely — those bypass guard and must set `org_id` explicit).
- Admin got 500 instead of the negotiated response while creating org-scoped record with no
  Impersonate Org active (422 JSON for JSON/AJAX callers, redirect-back 302 + flash for web):
  `UnresolvedOrgContextException` not registered in
  `bootstrap/app.php` exception handling, or local `try/catch` elsewhere in call
  stack swallow/rethrow it as different type before global handler.
- Console/queue creation of org-scoped rows failing with the exception: no
  request is in flight, so the code must pass `org_id` explicitly — host
  context never exists there.

## Edge Cases to Keep In Mind Before Changing Anything Here

- `credentials.org_id` uses `ON DELETE RESTRICT` (and `credentials.user_id`
  `ON DELETE CASCADE`). Organization with accounts is only soft-deletable;
  hard-delete must fail at DB level while any credential references it. Never
  "simplify" this FK to cascade — it would silently orphan or delete per-org
  accounts. (The pre-host `users.org_id` column no longer exists; do not
  reinstate it.)
- **Inactive account** (`credentials.status = 'inactive'`): fails login
  (provider checks status), fails remember-me recall
  (`retrieveByToken` requires status active), and `EnsureTenantAccess`
  logs an already-authenticated session out on the next request. Password
  change and deactivation both rotate `remember_token`
  (`Credential::rotateRememberToken()`).
- **Cross-host credential/session**: a session authenticated on portal A
  does not operate on portal B — `EnsureTenantAccess` demands an active
  credential on the HOST org per request. A remember-me cookie is likewise
  org-bound (token stored on the issuing credential).
- **Inactive Organization**: login/forgot fail with the generic error, and a
  password-reset token issued BEFORE deactivation is dead
  (`NewPasswordController` rejects the reset when `! $context->orgIsActive`).
  Landing + certificate lookup + auth routes stay reachable to guests;
  everything else redirects to `/`.
- `course_completion_rules.target_id` and `postable_type`/`postable_id` pairs on
  `forum_post_edits`/`forum_reports` are pseudo-polymorphic with **no real DB
  foreign key**. Integrity app-layer only. Migration change here cannot add real
  FK constraint; never attempt it, validate at application layer instead.
- `system_settings` has composite primary key `(setting_key, org_id)` where
  `org_id` is non-nullable with `default(0)` sentinel `SystemSetting::GLOBAL_ORG_ID = 0`
  for "global" settings (migration `2026_08_01_000021`) — settled, not an open
  question: a literal nullable composite PK is not achievable in MySQL/MariaDB
  (`PRIMARY KEY` columns are implicitly `NOT NULL`), so global lookups resolve
  via `forOrg()` mapping `null` to the `0` sentinel, and no FK is declared on
  `org_id` since `0` is not a real `organizations.id`.
- `OrgScope` must never be applied to `User` or `Credential`. On `User` doing so
  hide Admin rows from login and user-management queries; on `Credential` the
  org target comes from the host context explicitly
  (`scopeForOrg()`), never a global scope. If a query needs org filtering,
  scope it explicit, never add a global scope.
- Roles are **global**, not org-scoped (`spatie/laravel-permission`
  with `config('permission.teams') = false`). Never enable Spatie team/org-scoped
  permissions feature as shortcut for anything — it introduce second, competing
  tenancy mechanism alongside `org_id`.

## Auto-Update Protocol

Any change to
`OrgScope`, `ResolveOrgFromHost`, `EnsureTenantAccess`, `OrgContext`,
`Credential`/`credentials` shape, `RolesEnum`, org-scoped migrations/models, or
`UnresolvedOrgContextException` handling **must** update all three tenancy
skills (`tenancy-architecture`, `tenancy-conventions`, `tenancy-maintenance`) in
same change, before task done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affect what reviewer must check
  for org-scoped code, update its reference to these skills.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle chain)** —
one method drive create, edit, state change, delete, consequence — **not** by
module or feature. Consequences when maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as numbered
  steps inside chain method, maybe in file named after another module when
  journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name.
  Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new numbered
  step carrying own UI **and** DB assertion. New method only for independent
  negatives (403, cross-tenant, other actor); new file only for genuinely new
  journey.
- **Debugging failure**: stack trace point at step, not whole scenario — match
  line to its `// N.` comment. Late failure usually mean earlier step did not
  persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`; `DatabaseTruncation`
  inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` is
  suite-wide performance regression. Files, cache and session **not** reset
  between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
