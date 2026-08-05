---
name: auth-orgs-maintenance
description: >
  Debugging, testing, and edge-case guide for Aluno/Gestor CRUD (RF04) and the
  chunked CSV import (RF05/RN09). Use when a `MultiTenantStudentImportTest` or
  `UserCrudTest` is failing, an imported student is missing enrollment or has
  a duplicated User row, `UnresolvedOrgContextException` fires unexpectedly
  during import, or you're about to touch `UserImportService`,
  `UserController`, `UserPolicy`, or `CsvImporter.js` and need to know what
  else must change with it.
license: MIT
metadata:
  feature: auth-orgs
  role: maintenance
  specs:
    - spec/specs/04-auth-profile-organizations-and-user-management.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Auth/Orgs Maintenance

## Mandatory Test Coverage for This Module

These tests guard the Bucket C contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/MultiTenantStudentImportTest.php` — RN09: existing global
  e-mail only gains a new enrollment (no duplicate `User`, no password
  overwrite); new e-mail creates the `User` bound to the current `org_id`;
  chunk boundary at exactly 50 rows; malformed rows are skipped without
  aborting the batch; Admin with no `active_org_id` gets a 422 from the
  import endpoint.
- `tests/Feature/UserCrudTest.php` — Admin/Gestor CRUD scoping, a Gestor's
  submitted `org_id` is always ignored server-side, Aluno is forbidden from
  every `/users*` route.
- `tests/Browser/MultiTenantStudentImportTest.php` — E2E: upload a CSV,
  observe the chunked progress bar, verify the final course roster.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=MultiTenantStudentImportTest
vendor/bin/sail artisan test --filter=UserCrudTest
```

## RN09 — Multi-Org Adaptive Enrollment

`UserImportService::importChunk()` is the single place this rule is
implemented. Per row:

1. Look up the student **globally by e-mail** (`User::where('email', ...)`),
   not scoped by `org_id` — a student already active at a different
   Organization must be found.
2. If found: do **not** touch `password` or `org_id` on that row. Only ensure
   a `course_user` enrollment exists for the current chunk's `course_id`
   (`firstOrCreate`-style existence check before `attach()`, so re-uploading
   the same CSV twice is idempotent and never throws a pivot unique-constraint
   violation).
3. If not found: create the `User` with the resolved `org_id`, a random
   (never client-supplied) password, `aluno` role, then enroll.

If you ever need a second import entry point (e.g. an API import), reuse this
service — do not re-implement the "exists globally / reuse vs. create" branch
a second time, that duplication is exactly how RN09 regresses silently.

## Diagnosing "Import Created a Duplicate User" or "Overwrote a Password"

- Confirm the lookup in step 1 above is unscoped by `org_id`/`OrgScope` — it
  must query `User` directly (`User` intentionally never carries the
  `OrgScope` trait; see `tenancy-maintenance`), not through an org-scoped
  relation that would hide the other Organization's row and cause a
  false-negative "not found" → duplicate create.
- Confirm no code path calls `User::updateOrCreate(['email' => ...], [...])`
  for this flow — `updateOrCreate` would silently overwrite `password`/`org_id`
  on the matched row. The service always branches explicitly instead
  (`if (! $user) { create } `), by design.

## Diagnosing "Chunk Boundary" / Partial-Batch Bugs

- The chunk size (50) lives in exactly two places that must stay in sync:
  `UserImportService`/`ImportUsersChunkRequest` (`rows` max:50, enforced
  server-side as a defensive cap) and `CsvImporter.js`'s `chunkSize` property
  (the actual client-side splitter). If you ever tune the chunk size, change
  both — a client sending 200-row batches against a `max:50` request rule
  will get a 422 on every batch past the first, not a clean partial import.
- A CSV whose row count isn't a multiple of 50 must still fully import: the
  last chunk is simply shorter (`chunkRows()` uses `Array.slice`, no padding).
  Regression-test this with a row count like 51 or 99, not only exact
  multiples of 50.
- Malformed rows (missing/blank `name` or `email`, or an e-mail that fails
  `filter_var(..., FILTER_VALIDATE_EMAIL)`) are recorded in the `skipped`
  array and otherwise ignored — they must never throw and abort the rest of
  the chunk. If you add a new required column, extend this per-row check
  inside the `foreach`, do not add a request-level `required` rule for it
  (that would 422 the *entire* chunk over one bad row instead of skipping
  just that row).

## Client-Driven Chunking — Why the Server Never Sees the Raw File

Per the RF05 spec text ("streaming em chunks AJAX de 50 registros"),
`CsvImporter.js` reads the selected `File` with `FileReader`, splits it into
row objects with a small manual parser (no PapaParse/other dependency, per
CLAUDE.md's "don't add dependencies without approval"), and POSTs each 50-row
batch as JSON through `HttpClient`. `ImportUsersChunkRequest`/
`UserImportController::chunk()` never receive multipart file bytes — only
`course_id` + `rows` (+ optional `filename` metadata used only for a
extension sanity check, not content validation). If a future requirement
needs true server-side streaming of arbitrarily large files (e.g. an
API-only bulk import with no browser involved), that is a different code
path — do not bolt a raw file upload onto this endpoint, its request/response
shape assumes small, client-chunked JSON payloads.

## `UnresolvedOrgContextException` During Import/CRUD

`UserController`/`UserImportController` each carry a small
`resolveOrgId(Request $request): int` method that mirrors
`OrgScope::booted()`'s resolution order (`$user->org_id ?? session(
'active_org_id')`) and throw `UnresolvedOrgContextException` on failure —
`User` itself is not `OrgScope`d (see its docblock), so this has to be
reimplemented at the controller boundary rather than inherited from a model
trait. If you extract a shared helper for this later, keep the exact same
`??` order and exception message shape used in `tenancy-conventions`, so
`bootstrap/app.php`'s global handler keeps producing the same 422 JSON /
redirect-back behavior for both Buckets B and C.

## `UserPolicy` — Compares `org_id`, Not Just Role

Unlike `OrganizationPolicy` (a plain role check), `UserPolicy` additionally
compares the target user's `org_id` against the acting user's resolved
context (Admin: `session('active_org_id')`; Gestor: `$user->org_id`). A
Gestor and an Admin impersonating a *different* Org must both get a 403 on
another Org's user, not a 404 (the row exists, route-model-binding finds it —
authorization is what fails). See `auth-orgs-conventions` for the shared
`Gate::authorize()` pattern this policy plugs into.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change to
`UserController`, `UserImportController`, `UserImportService`, `UserPolicy`,
the `users*` routes, or `CsvImporter.js` **must** update all three auth-orgs
skills (`auth-orgs-architecture`, `auth-orgs-conventions`,
`auth-orgs-maintenance`) in the same change, before the task is considered
done. Also re-check:

- `.agents/agents/code-reviewer.md` — if the change affects what a reviewer
  must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fails the build if
  any of the three `auth-orgs-*` skills is missing.

## Related Specs

- `spec/specs/04-auth-profile-organizations-and-user-management.md` — RF04,
  RF05, RN09.
- `tenancy-maintenance` — the underlying `OrgScope`/`RolesEnum` contract this
  module builds on.
- `spec/specs/03-agentic-harness-and-self-updating-skills.md` (this
  auto-update protocol).
