---
name: tenancy-maintenance
description: >
  Debug, test, edge-case guide for multitenancy module (org isolation,
  Impersonate Org, UnresolvedOrgContextException). Use when test leak data
  across organizations, Admin-created record miss org_id,
  OrgScopeUnresolvedContextTest or tenant-isolation test fail, or before touch
  OrgScope/RolesEnum/org-scoped migrations and need know what else must change
  with it.
license: MIT
metadata:
  feature: tenancy
  role: maintenance
---

# Tenancy Maintenance

## Mandatory Test Coverage for This Module

These tests guard tenancy contract. Must stay green (PHPUnit, per project
convention — no Pest):

- `tests/Feature/OrgScope/OrgScopeUnresolvedContextTest.php` — assert
  `UnresolvedOrgContextException` thrown (and mapped to HTTP 422) when Admin
  with no Impersonate Org active create org-scoped record.
- `tests/Feature/OrgScope/OrgScopeTenantIsolationTest.php` — assert Gestor of
  Org A cannot see/query records of Org B through org-scoped model.
- `tests/Feature/OrgScope/OrgScopeImpersonateOrgTest.php` — assert Admin with
  `session('active_org_id')` set see only that org records, and Admin without it
  set see across all orgs.
- `tests/Feature/Auth/RolesMiddlewareTest.php` — `role:admin` / `role:gestor` /
  `role:aluno` gate checks.
- `tests/Unit/Enums/RolesEnumTest.php` — `RolesEnum` values/labels.

Run narrowest first after touching `OrgScope`, `RolesEnum`, or any org-scoped
migration:

```bash
vendor/bin/sail artisan test --filter=OrgScope
vendor/bin/sail artisan test --compact tests/Feature/Auth/RolesMiddlewareTest.php
```

## Diagnosing "Data Leaking Across Organizations"

1. Confirm model really `use`s `OrgScope`. Cascade-inherited models (`Module`,
   `Lesson`, `Quiz`, ...) carry no trait by design; query built direct against
   them without joining/scoping through parent `Course` is not tenant-filtered.
   Expected, not bug — scope through relation (`$course->modules()`), not
   `Module::all()`.
2. Confirm caller authenticated. `OrgScope` global scope is no-op when
   `Auth::user()` null (by design, for public routes like certificate
   validation). Background job or console command running without authenticated
   user see unfiltered data unless it manually scope query.
3. For Admin, check whether `session('active_org_id')` really set. Admin with no
   active Impersonate Org see *all* organizations by design — not leak,
   documented "global view" behavior.

## Diagnosing "Record Created With Wrong or Missing `org_id`"

- Silently got `org_id = null` on org-scoped table: regression. Current trait
  must throw `UnresolvedOrgContextException` instead. Check `OrgScope::booted()`
  `creating` hook not bypassed (via `forceCreate()`, `insert()`, or mass-insert
  query builder call skipping Eloquent events entirely — those bypass guard and
  must set `org_id` explicit).
- Admin got 500 instead of 422 while creating org-scoped record with no
  Impersonate Org active: `UnresolvedOrgContextException` not registered in
  `bootstrap/app.php` exception handling, or local `try/catch` elsewhere in call
  stack swallow/rethrow it as different type before global handler.

## Edge Cases to Keep In Mind Before Changing Anything Here

- `users.org_id` use `ON DELETE RESTRICT`, not `CASCADE`. Organization with zero
  users still soft-deletable; hard-delete of Organization while any user still
  reference it must fail at DB level. Never "simplify" this FK to cascade — it
  would silently orphan or delete user accounts.
- `course_completion_rules.target_id` and `postable_type`/`postable_id` pairs on
  `forum_post_edits`/`forum_reports` are pseudo-polymorphic with **no real DB
  foreign key**. Integrity app-layer only. Migration change here cannot add real
  FK constraint; never attempt it, validate at application layer instead.
- `system_settings` has composite primary key `(setting_key, org_id)` where
  `org_id` nullable for "global" settings. `PRIMARY KEY` column implicitly
  `NOT NULL` in MySQL/MariaDB, so literal nullable composite PK not achievable
  as specified. Confirm the settled resolution (sentinel `org_id = 0` for
  global vs plain unique index) before writing any code assuming one behavior or
  other for global settings lookups.
- `OrgScope` must never be applied to `User`. Doing so hide Admin/Aluno rows
  (`org_id = null`) from login and user-management queries. If future change
  make `User` need org filtering for some specific query, scope that query
  explicit (`where('org_id', ...)`), never add global scope to model.
- Roles are **global**, not org-scoped (`spatie/laravel-permission`
  with `config('permission.teams') = false`). Never enable Spatie team/org-scoped
  permissions feature as shortcut for anything — it introduce second, competing
  tenancy mechanism alongside `org_id`.

## Auto-Update Protocol

Any change to
`OrgScope`, `RolesEnum`, org-scoped migrations/models, or
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
