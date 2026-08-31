---
name: auth-orgs-maintenance
description: >
  Debug, test, edge-case guide for Aluno/Gestor CRUD (RF04) and chunked CSV
  import (RF05/RN09). Use when `MultiTenantStudentImportTest` or
  `UserCrudTest` fails, imported student misses enrollment or duplicates a
  User row, `UnresolvedOrgContextException` fires during import, or before
  touching `UserImportService`, `UserController`, `UserPolicy`,
  `CsvImporter.js`.
license: MIT
metadata:
  feature: auth-orgs
  role: maintenance
  specs:
    - spec/specs/04-auth-profile-organizations-and-user-management.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Auth/Orgs Maintenance

## Mandatory Test Coverage

Guard the Bucket C contract. PHPUnit, no Pest. Keep green:

- `tests/Feature/MultiTenantStudentImportTest.php` — RN09: existing global e-mail gains only a new enrollment (no duplicate `User`, no password overwrite); new e-mail creates `User` bound to current `org_id`; chunk boundary at exactly 50 rows; malformed rows skipped without aborting batch; Admin with no `active_org_id` gets 422.
- `tests/Feature/UserCrudTest.php` — Admin/Gestor CRUD scoping; Gestor-submitted `org_id` always ignored server-side; Aluno forbidden on every `/users*` route.
- `tests/Browser/MultiTenantStudentImportTest.php` — E2E: upload CSV, chunked progress bar, final course roster.
- `tests/Feature/Admin/UserAdminManagementTest.php` (SPEC-002) — cross-org listing with all 3 roles; screen reachable without any Impersonate Org context; every filter (name/email/org_id/status/role/created_at range) individually and combined, still paginated; Gestor/Aluno 403 on every `admin.users.*` route (middleware, not just Policy); guest redirect; show/edit views; full-profile update including `org_id`/role across all 3 `RolesEnum` values; activate/deactivate + `user.status_changed` audit rows; self-deactivation/self-demotion/self-deletion guards; destroy + audit row + `certificates`/`invitation_links` RESTRICT guards; regression that `users.index` stays org-scoped to aluno/gestor only.
- `tests/Unit/Policies/UserPolicyGlobalAbilitiesTest.php` (SPEC-002) — `viewAnyGlobal`/`viewGlobal`/`updateGlobal`/`deleteGlobal` (Admin-only, no org dependency, self-delete blocked) plus a regression guard that the original `sharesOrgContext()`-based abilities are unchanged.
- `tests/Browser/AdminUserManagementTest.php` (SPEC-002) — full lifecycle as plain Admin with no impersonation: cross-org listing, filter by org, deactivate via confirm modal (`data-status` assertion, not badge text), delete via confirm modal, nav-item visibility restricted to Admin only.

Run narrowest first:

```bash
vendor/bin/sail artisan test --filter=MultiTenantStudentImportTest
vendor/bin/sail artisan test --filter=UserCrudTest
vendor/bin/sail artisan test --filter=UserAdminManagementTest
vendor/bin/sail artisan test --filter=UserPolicyGlobalAbilitiesTest
```

## RN09 — Multi-Org Adaptive Enrollment

`UserImportService::importChunk()` = only place this rule lives. Per row:

1. Look up student **globally by e-mail** (`User::where('email', ...)`), not scoped by `org_id` — a student active at another Organization must be found.
2. Found: do **not** touch `password` or `org_id`. Only ensure `course_user` enrollment exists for the chunk's `course_id` (`firstOrCreate`-style existence check before `attach()`), so re-uploading the same CSV is idempotent and never hits a pivot unique-constraint violation.
3. Not found: create `User` with resolved `org_id`, random (never client-supplied) password, `aluno` role, then enroll.

Second import entry point (API import) reuses this service. Re-implementing the "exists globally / reuse vs create" branch is exactly how RN09 regresses silently.

## Duplicate User or Overwritten Password

- Step 1 lookup must be unscoped by `org_id`/`OrgScope` — query `User` directly (`User` never carries `OrgScope`, see `tenancy-maintenance`), not through an org-scoped relation that hides the other Org's row and produces false-negative "not found" then duplicate create.
- No code path may call `User::updateOrCreate(['email' => ...], [...])` here — it silently overwrites `password`/`org_id` on the matched row. Service branches explicitly (`if (! $user) { create }`) by design.

## Chunk Boundary / Partial-Batch Bugs

- Chunk size 50 lives in two places that must stay in sync: `UserImportService`/`ImportUsersChunkRequest` (`rows` max:50, defensive server cap) and `CsvImporter.js` `chunkSize` (actual client splitter). Tuning it means changing both — client sending 200-row batches against `max:50` gets 422 on every batch past the first, not a clean partial import.
- CSV whose row count is not a multiple of 50 must fully import: last chunk is shorter (`chunkRows()` uses `Array.slice`, no padding). Regression-test with 51 or 99, not only exact multiples.
- Malformed rows (blank/missing `name` or `email`, or e-mail failing `filter_var(..., FILTER_VALIDATE_EMAIL)`) go into the `skipped` array and are ignored — never throw, never abort the chunk. New required column extends this per-row check inside the `foreach`. Do not add a request-level `required` rule — that 422s the *entire* chunk over one bad row.

## Client-Driven Chunking — Server Never Sees the Raw File

Per RF05 ("streaming em chunks AJAX de 50 registros"), `CsvImporter.js` reads the `File` with `FileReader`, splits into row objects with a small manual parser (no PapaParse — CLAUDE.md forbids new dependencies), POSTs each 50-row batch as JSON via `HttpClient`. `ImportUsersChunkRequest`/`UserImportController::chunk()` never get multipart bytes — only `course_id` + `rows` (+ optional `filename` used for extension sanity check, not content validation). True server-side streaming of huge files (API-only bulk import) = different code path. Do not bolt raw file upload onto this endpoint; its request/response shape assumes small client-chunked JSON.

## `UnresolvedOrgContextException` During Import/CRUD

`UserController`/`UserImportController` each carry `resolveOrgId(Request $request): int` mirroring `OrgScope::booted()` order (`$user->org_id ?? session('active_org_id')`), throwing `UnresolvedOrgContextException` on failure. `User` is not `OrgScope`d (see its docblock), so this is reimplemented at the controller boundary, not inherited. Extracting a shared helper later: keep the exact `??` order and exception message shape from `tenancy-conventions`, so `bootstrap/app.php`'s handler keeps producing the same 422 JSON / redirect-back for Buckets B and C.

## `UserPolicy` — Compares `org_id`, Not Just Role

Unlike `OrganizationPolicy` (plain role check), `UserPolicy` also compares target user's `org_id` against acting user's resolved context (Admin: `session('active_org_id')`; Gestor: `$user->org_id`). Gestor and Admin impersonating a *different* Org both get 403 on another Org's user, not 404 — row exists, route-model-binding finds it, authorization fails. Plugs into the `Gate::authorize()` pattern in `auth-orgs-conventions`.

## SPEC-002 — Global Admin User-Management Screen Edge Cases

- **Badge text is uppercased by CSS, not by the string in the Blade file.** `.badge` has `text-transform: uppercase`, and Dusk reads *rendered* text — `assertSeeIn('@admin-user-status-1', 'Ativo')` fails; it must assert `'ATIVO'`, or (preferred, what `admin/users/index.blade.php` actually does) read the `data-status`/`data-role` attribute instead of the visible text. Same trap applies to any other `<x-ui.badge>` on a new screen.
- **`admin.users.*` routes must live in the `role:admin`-only group**, never `role:admin|gestor` — putting them in the wrong group makes the "inacessível a Gestores" acceptance criterion pass by Policy alone, which regresses silently if the Policy is ever loosened. A Dusk/Feature test hitting the route as Gestor must assert a 403 that happens before the controller even runs.
- **Self-action guards are 403s inside the controller, not validation errors** — `UserAdminController::update()`/`updateStatus()` `abort(403, ...)` when the acting Admin targets their own row for deactivation or a role-change away from `admin`; `UserPolicy::deleteGlobal()` blocks self-deletion at the Policy layer instead. Getting these two layers mixed up (e.g. moving the self-delete check into the controller only) means a test asserting `assertForbidden()` before any DB write would instead see a partial mutation.
- **`destroy()` needs both RESTRICT-FK pre-checks or it 500s.** `certificates.user_id` and `invitation_links.created_by` are both `ON DELETE RESTRICT`; a User with either related row throws a dedicated exception (`UserHasIssuedCertificatesException`/`UserHasCreatedInvitationLinksException`) instead of letting `$user->delete()` crash raw. The `invitation_links` check must use `->withoutGlobalScope('org')` — `InvitationLink` is `OrgScope`d, so without the bypass an Admin with no active impersonation (the normal state on this screen) silently sees zero links and the delete proceeds straight into the DB-level crash.
- **`UpdateUserAdminRequest` forces `org_id` to `null` when `role === admin`** in `prepareForValidation()` — a regression here (e.g. moving that logic into `rules()`, which runs after normalization) lets a stale `org_id` slip through validation on a role-change-to-admin submission and leaves the row `org_id`-set with an admin role, which nothing else in the app expects.

## `UserHomeResolver` Sync on Role Change (BUG-001)

`App\Services\UserHomeResolver::resolve()` = single source of truth for role-based post-login and guest-guard redirects. New role in `RolesEnum` needing its own dashboard **must** update this method, else it falls through to the `student.courses.index` catch-all. Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` delegate here, so one edit covers both paths.

After editing:
```bash
vendor/bin/sail artisan test --filter=LoginTest
```
`tests/Feature/Auth/LoginTest.php` asserts role-specific redirect targets and catches a missed update.

## Login Screen Selector/Copy Contract Lives in the Dusk `LoginTest`

`tests/Browser/Auth/LoginTest.php` additionally guards the *screen*, not just the redirect:

- `test_login_screen_exposes_the_selector_contract_and_offers_no_signup_path` — all six `dusk="login-*"`/`forgot-password-link` hooks, the input `type`s, the forgot-password `href`, and the **absence** of any `/register` link or "Criar conta"/"Cadastre-se" copy (see `auth-orgs-conventions`, guest shell markup contract).
- `test_login_credential_rejections` — a wrong password **and** a non-existent e-mail must produce the *same* generic message; a failure here usually means someone "improved" the copy into an account-enumeration oracle.

Failing after a Blade edit? Rebuild first (`vendor/bin/sail npm run build`), and check the selector was not moved onto a wrapper element by a component swap — `DuskSelectorContractTest` (PHPUnit) catches the moved/dropped case faster than the browser run.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change to `UserController`, `Admin\UserAdminController`, `UserImportController`, `UserImportService`, `UserPolicy`, `UpdateUserAdminRequest`, `users*`/`admin.users.*` routes, `CsvImporter.js`, or the guest-shell auth views (`resources/views/auth/login.blade.php`, `resources/views/layouts/guest.blade.php`, `resources/views/components/layout/guest-panel.blade.php`) **must** update all three auth-orgs skills (`auth-orgs-architecture`, `auth-orgs-conventions`, `auth-orgs-maintenance`) in the same change before the task is done. Also:

- `.agents/agents/code-reviewer.md` — if the change alters what a reviewer checks for this module.
- `vendor/bin/sail artisan harness:check-skills` — fails the build if any `auth-orgs-*` skill is missing.

## Related

- `spec/specs/04-auth-profile-organizations-and-user-management.md` — RF04, RF05, RN09.
- `tenancy-maintenance` — underlying `OrgScope`/`RolesEnum` contract.
- `spec/specs/03-agentic-harness-and-self-updating-skills.md` — auto-update protocol.

---

## E2E Coverage Lives in Lifecycle Chains

`tests/Browser/` groups by **user journey (lifecycle chain)** — one method drives create, edit, state change, delete, consequence. Not by module, spec, or use case.

- **Find coverage**: chain methods may sit in a file named after another module when the journey crosses boundaries. Search `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name. Missing per-module file is **not** a gap.
- **Add coverage**: extend the journey's chain with a numbered step carrying UI **and** DB assertion. New method only for independent negatives (403, cross-tenant, other actor). New file only for genuinely new journey.
- **Debug**: stack trace points at a step — match line to its `// N.` comment. Late failure usually means earlier step did not persist.
- **Database**: no DB trait in `tests/Browser/*`; `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` = suite-wide slowdown. Files, cache, session not reset between methods.

Full rule: `testing-conventions`. Chain debug: `testing-maintenance`.
