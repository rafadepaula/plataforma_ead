---
name: tenancy-maintenance
description: >
  Debugging, testing, and edge-case guide for the multitenancy module (org
  isolation, Impersonate Org, UnresolvedOrgContextException). Use when a test
  is leaking data across organizations, an Admin-created record is missing its
  org_id, OrgScopeUnresolvedContextTest or a tenant-isolation test is failing,
  or you're about to touch OrgScope/RolesEnum/the org-scoped migrations and
  need to know what else must change with it.
license: MIT
metadata:
  feature: tenancy
  role: maintenance
  specs:
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Tenancy Maintenance

## Mandatory Test Coverage for This Module

These tests exist specifically to guard the tenancy contract and must stay
green (PHPUnit, per project convention — no Pest):

- `tests/Feature/OrgScope/OrgScopeUnresolvedContextTest.php` — asserts
  `UnresolvedOrgContextException` is thrown (and mapped to HTTP 422) when an
  Admin with no Impersonate Org active creates an org-scoped record.
- `tests/Feature/OrgScope/OrgScopeTenantIsolationTest.php` — asserts a Gestor
  of Org A cannot see/query records belonging to Org B through an org-scoped
  model.
- `tests/Feature/OrgScope/OrgScopeImpersonateOrgTest.php` — asserts an Admin
  with `session('active_org_id')` set sees only that org's records, and an
  Admin without it set sees across all orgs.
- `tests/Feature/Auth/RolesMiddlewareTest.php` — `role:admin` / `role:gestor`
  / `role:aluno` gate checks.
- `tests/Unit/Enums/RolesEnumTest.php` — `RolesEnum` values/labels.

Run the narrowest of these first after touching `OrgScope`, `RolesEnum`, or
any org-scoped migration:

```bash
vendor/bin/sail artisan test --filter=OrgScope
vendor/bin/sail artisan test --compact tests/Feature/Auth/RolesMiddlewareTest.php
```

## Diagnosing "Data Leaking Across Organizations"

1. Confirm the model actually `use`s `OrgScope` — cascade-inherited models
   (`Module`, `Lesson`, `Quiz`, ...) do not carry the trait by design; a query
   built directly against them without joining/scoping through the parent
   `Course` will not be tenant-filtered. That is expected, not a bug — scope
   through the relation (`$course->modules()`), not `Module::all()`.
2. Confirm the caller is authenticated. `OrgScope`'s global scope is a no-op
   when `Auth::user()` is null (by design, for public routes like certificate
   validation) — a background job or console command running without an
   authenticated user will see unfiltered data unless it manually scopes the
   query.
3. For an Admin, check whether `session('active_org_id')` is actually set. An
   Admin with no active Impersonate Org sees *all* organizations by design —
   this is not a leak, it's the documented "global view" behavior.

## Diagnosing "Record Created With Wrong or Missing `org_id`"

- If it silently got `org_id = null` on an org-scoped table: this indicates a
  regression — the current trait must throw `UnresolvedOrgContextException`
  instead. Check that `OrgScope::booted()`'s `creating` hook was not
  bypassed (e.g. via `forceCreate()`, `insert()`, or a mass-insert query
  builder call that skips Eloquent events entirely — those bypass this guard
  and must set `org_id` explicitly).
- If an Admin got a 500 instead of a 422 while creating an org-scoped record
  with no Impersonate Org active: `UnresolvedOrgContextException` is not
  registered in `bootstrap/app.php`'s exception handling, or a local
  `try/catch` elsewhere in the call stack is swallowing/rethrowing it as a
  different type before it reaches the global handler.

## Edge Cases to Keep In Mind Before Changing Anything Here

- `users.org_id` uses `ON DELETE RESTRICT`, not `CASCADE`. An Organization
  with zero users can still be soft-deleted; hard-deleting an Organization
  while any user still references it must fail at the DB level. Don't
  "simplify" this FK to cascade — it would silently orphan or delete user
  accounts.
- `course_completion_rules.target_id` and the `postable_type`/`postable_id`
  pairs on `forum_post_edits`/`forum_reports` are pseudo-polymorphic with **no
  real DB foreign key** — integrity is app-layer only. A migration change here
  cannot add a real FK constraint; don't attempt it, validate at the
  application layer instead.
- `system_settings` has a composite primary key `(setting_key, org_id)` where
  `org_id` is nullable for "global" settings. A `PRIMARY KEY` column is
  implicitly `NOT NULL` in MySQL/MariaDB, so a literal nullable composite PK is
  not achievable as specified — confirm with Bucket A's resolution (sentinel
  `org_id = 0` for global vs. a plain unique index) before writing any code
  that assumes one behavior or the other for global settings lookups.
- `OrgScope` must never be applied to `User` — doing so would hide Admin/Aluno
  rows (`org_id = null`) from login and user-management queries. If a future
  change makes `User` need org filtering for some specific query, scope that
  query explicitly (`where('org_id', ...)`), don't add the global scope to the
  model.
- Roles are **global**, not org-scoped, per spec (`spatie/laravel-permission`
  with `config('permission.teams') = false`). Do not enable Spatie's
  team/org-scoped permissions feature as a shortcut for anything — it
  introduces a second, competing tenancy mechanism alongside `org_id`.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change to
`OrgScope`, `RolesEnum`, the org-scoped migrations/models, or the
`UnresolvedOrgContextException` handling **must** update all three tenancy
skills (`tenancy-architecture`, `tenancy-conventions`, `tenancy-maintenance`)
in the same change, before the task is considered done. Also re-check:

- `spec/docs/multitenancy.md` — keep its `OrgScope` code block byte-identical
  to `spec/specs/00-architecture-database-and-guardrails.md` §3.
- `.agents/agents/code-reviewer.md` — if the change affects what a reviewer
  must check for org-scoped code, update its reference to these skills.

## Related Specs

- `spec/specs/00-architecture-database-and-guardrails.md` §3 (`OrgScope`), §4
  (`RolesEnum`), §5 (coverage/Dusk guardrails).
- `spec/specs/03-agentic-harness-and-self-updating-skills.md` (this
  auto-update protocol).
