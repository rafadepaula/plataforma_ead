---
name: auth-orgs-maintenance
description: >
  Debug, test, edge-case guide for host-scoped login/password flows
  (HostScopedLoginTest, PasswordResetHostTest, PasswordUpdateTest),
  Aluno/Gestor CRUD and chunked CSV import. Use when
  `MultiTenantStudentImportTest` or `UserCrudTest` fails, a login fails
  on the right host (or passes on the wrong one), remember-me or throttle
  misbehaves per org, an imported student misses enrollment or duplicates
  a User row, `UnresolvedOrgContextException` fires during import, or
  before touching `UserImportService`, `UserController`, `UserPolicy`,
  `OrgCredentialUserProvider`, `CsvImporter.js`.
license: MIT
metadata:
  feature: auth-orgs
  role: maintenance
---

# Auth/Orgs Maintenance

## Mandatory Test Coverage

Guard this module's contract. PHPUnit, no Pest. Keep green:

- `tests/Feature/Auth/HostScopedLoginTest.php` — THE auth contract suite:
  login succeeds on the host org where the credential lives; same person
  without an account on host B fails with the generic error (like a wrong
  password); wrong password generic; INACTIVE credential fails; login on
  an inactive org fails even with a valid credential; state zero accepts
  only the global Admin credential and rejects an org credential;
  remember-me is owned by the credential of the portal where login
  happened (token rotated there, other portal's token untouched, token
  invalid cross-org, inactive account never reacquires by cookie); throttle
  is per org.
- `tests/Feature/Auth/PasswordResetHostTest.php` — reset changes ONLY the
  host org's password and preserves the other org's; pre-existing token is
  blocked on an inactive org; forgot without an account in the host org
  fails generic; forgot on inactive org fails generic; state-zero Admin
  can reset.
- `tests/Feature/PasswordUpdateTest.php` — `current_password` validates
  against the host credential (`User::getAuthPassword()`); wrong current
  password leaves the credential unchanged; password change logs out
  other sessions (`AuthenticateSession`); update rate-limited after six
  attempts.
- `tests/Feature/MultiTenantStudentImportTest.php` — the multi-org
  adaptive-enrollment rule: existing global e-mail gains only a new
  enrollment + credential provisioning (no duplicate `User`, no password
  overwrite), inactive account reactivated in the importing org; new
  e-mail creates `User` + org credential; chunk boundary at exactly 50
  rows; malformed rows skipped without aborting batch.
- `tests/Feature/UserCrudTest.php` — Admin/Gestor CRUD scoping; Admin
  impersonating an org ignores any `org_id` sent in the request; Admin
  creating a user with an e-mail from another org reuses the person
  (`ProvisionOrgAccountAction`); Admin without active org context gets the
  negotiated `UnresolvedOrgContextException` response; Aluno forbidden on
  every `/users*` route.
- `tests/Browser/MultiTenantStudentImportTest.php` — E2E: upload CSV, chunked progress bar, final course roster.
- `tests/Feature/Admin/UserAdminManagementTest.php` — cross-org listing with all 4 roles; screen reachable without any Impersonate Org context; every filter (name/email/org_id/status/role/created_at range) individually and combined, still paginated; Gestor/Aluno 403 on every `admin.users.*` route (middleware, not just Policy); guest redirect; show/edit views; full-profile update across all 4 `RolesEnum` values; activate/deactivate + `user.status_changed` audit rows; self-deactivation/self-demotion/self-deletion guards; destroy + audit row + `certificates`/`invitation_links` RESTRICT guards; regression that `users.index` stays org-scoped to aluno/gestor only.
- `tests/Unit/Policies/UserPolicyGlobalAbilitiesTest.php` — `viewAnyGlobal`/`viewGlobal`/`updateGlobal`/`deleteGlobal` (Admin-only, no org dependency, self-delete blocked) plus a regression guard that the original `sharesOrgContext()`-based abilities are unchanged.
- `tests/Browser/AdminUserManagementTest.php` — full lifecycle as plain Admin with no impersonation: cross-org listing, filter by org, deactivate via confirm modal (`data-status` assertion, not badge text), delete via confirm modal, nav-item visibility restricted to Admin only.

Run narrowest first:

```bash
vendor/bin/sail artisan test --compact tests/Feature/Auth/HostScopedLoginTest.php
vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetHostTest.php
vendor/bin/sail artisan test --compact tests/Feature/PasswordUpdateTest.php
vendor/bin/sail artisan test --filter=MultiTenantStudentImportTest
vendor/bin/sail artisan test --filter=UserCrudTest
vendor/bin/sail artisan test --filter=UserAdminManagementTest
vendor/bin/sail artisan test --filter=UserPolicyGlobalAbilitiesTest
```

## Multi-Org Adaptive Enrollment

`UserImportService::importChunk()` = only place this rule lives. Per row:

1. Look up student **globally by e-mail** (`User::where('email', ...)`), not scoped by org — a student active at another Organization must be found.
2. Found: do **not** touch the person's other-org credentials. Ensure the `credentials` row exists for the importing org (`firstOrCreate` on `(user_id, org_id)` with a random, never-client-supplied password) and reactivate it if `status = inactive`; only then ensure the `course_user` enrollment exists for the chunk's `course_id`, so re-uploading the same CSV is idempotent.
3. Not found: create the `User` (person), provision the credential for the resolved org with a random password (`aluno` role), then enroll. The student regains access through the portal's forgot-password flow.

Second import entry point (API import) reuses this service. Re-implementing the "exists globally / reuse vs create" branch is exactly how this rule regresses silently.

## Duplicate User or Overwritten Password

- Step 1 lookup must be unscoped by org/`OrgScope` — query `User` directly (`User` never carries `OrgScope`, see `tenancy-maintenance`), not through an org-scoped relation that hides the other Org's row and produces false-negative "not found" then duplicate create.
- No code path may call `User::updateOrCreate(['email' => ...], [...])` here — `users` no longer even carries `password`/`org_id`; the danger is a silent credential rewrite. Service/action branches explicitly (`if (! $user) { create }`) by design (`ProvisionOrgAccountAction`, `UserImportService`).

## Chunk Boundary / Partial-Batch Bugs

- Chunk size 50 lives in two places that must stay in sync: `UserImportService`/`ImportUsersChunkRequest` (`rows` max:50, defensive server cap) and `CsvImporter.js` `chunkSize` (actual client splitter). Tuning it means changing both — client sending 200-row batches against `max:50` gets 422 on every batch past the first, not a clean partial import.
- CSV whose row count is not a multiple of 50 must fully import: last chunk is shorter (`chunkRows()` uses `Array.slice`, no padding). Regression-test with 51 or 99, not only exact multiples.
- Malformed rows (blank/missing `name` or `email`, or e-mail failing `filter_var(..., FILTER_VALIDATE_EMAIL)`) go into the `skipped` array and are ignored — never throw, never abort the chunk. New required column extends this per-row check inside the `foreach`. Do not add a request-level `required` rule — that 422s the *entire* chunk over one bad row.

## Client-Driven Chunking — Server Never Sees the Raw File

`CsvImporter.js` streams the import in AJAX chunks of 50 records: it reads
the `File` with `FileReader`, splits into row objects with a small manual parser (no PapaParse — CLAUDE.md forbids new dependencies), POSTs each 50-row batch as JSON via `HttpClient`. `ImportUsersChunkRequest`/`UserImportController::chunk()` never get multipart bytes — only `course_id` + `rows` (+ optional `filename` used for extension sanity check, not content validation). True server-side streaming of huge files (API-only bulk import) = different code path. Do not bolt raw file upload onto this endpoint; its request/response shape assumes small client-chunked JSON.

## `UnresolvedOrgContextException` During Import/CRUD

`UserController`, `UserImportController`, `GestorStudentController`, and `GestorProfessorController` share tenant-context resolution via the `ResolvesOrgContext::resolveOrgId()` trait (`App\Http\Controllers\Concerns\ResolvesOrgContext`), which mirrors `OrgScope`'s creating hook order (Admin: `session('active_org_id')`; others: `OrgContext::current()->orgId()`) and throws `UnresolvedOrgContextException` on failure. `User` is not `OrgScope`d (see its docblock), so this is resolved at the controller boundary, not inherited. Keep the exact branch order and exception message shape from `tenancy-conventions`, so `bootstrap/app.php`'s handler keeps producing the same negotiated response for all four flows (422 JSON for JSON/AJAX callers, redirect-back 302 + flash for web).

## `UserPolicy` — Compares Credential Membership, Not Just Role

Unlike `OrganizationPolicy` (plain role check), `UserPolicy::sharesOrgContext()` asks whether the TARGET person holds a `credentials` account in the ACTING user's server-resolved org (`holdsAccountIn()`): Admin → impersonated `session('active_org_id')`; Gestor → `OrgContext::current()->orgId()`. Gestor and Admin impersonating a *different* Org both get 403 on another Org's person, not 404 — row exists, route-model-binding finds it, authorization fails. Plugs into the `Gate::authorize()` pattern in `auth-orgs-conventions`. Professor coverage lives alongside: `viewAnyStudents`/`updateStudent`/`deleteStudent` gate the Gestor's Aluno-only directory (target must be `aluno` holding an account in the same org), and the Gestor's Professor directory (`GestorProfessorController`, `role:professor` accounts in the same org) reuses `viewAny`/`update`/`delete` plus an explicit target-is-Professor check in the controller.

## Global Admin User-Management Screen Edge Cases

- **Badge text is uppercased by CSS, not by the string in the Blade file.** `.badge` has `text-transform: uppercase`, and Dusk reads *rendered* text — `assertSeeIn('@admin-user-status-1', 'Ativo')` fails; it must assert `'ATIVO'`, or (preferred, what `admin/users/index.blade.php` actually does) read the `data-status`/`data-role` attribute instead of the visible text. Same trap applies to any other `<x-ui.badge>` on a new screen.
- **`admin.users.*` routes must live in the `role:admin`-only group**, never `role:admin|gestor` — putting them in the wrong group makes the "inacessível a Gestores" acceptance criterion pass by Policy alone, which regresses silently if the Policy is ever loosened. A Dusk/Feature test hitting the route as Gestor must assert a 403 that happens before the controller even runs.
- **Self-action guards are 403s inside the controller, not validation errors** — `UserAdminController::update()`/`updateStatus()` `abort(403, ...)` when the acting Admin targets their own row for deactivation or a role-change away from `admin`; `UserPolicy::deleteGlobal()` blocks self-deletion at the Policy layer instead. Getting these two layers mixed up (e.g. moving the self-delete check into the controller only) means a test asserting `assertForbidden()` before any DB write would instead see a partial mutation.
- **`destroy()` needs the RESTRICT-FK pre-check or it 500s.** `certificates.user_id` is `ON DELETE RESTRICT`; a User with certificates throws a dedicated exception (`UserHasIssuedCertificatesException`) instead of letting `$user->delete()` crash raw. (The second pre-check, `invitation_links.created_by`, died with the shareable-link table — `student_invitations.created_by` is `ON DELETE SET NULL` and never blocks a delete.)
- **`UpdateUserAdminRequest` no longer edits org membership or account status** — a regression reintroducing an `org_id`/`status` field there lets the global screen reach across portals and desync per-org accounts. The nullable `password` it accepts resets EVERY credential of the person and rotates each `remember_token`; a regression here (e.g. skipping `rotateRememberToken()`) leaves "remembered" devices alive on deactivated/rewritten accounts.
- **Global status flip is all-credentials** (`admin.users.status` iterates `$user->credentials()->get()`): the coarse person-level signal is `User::hasActiveAccount()`, while any per-portal truth stays in that org's credential row.

## `UserHomeResolver` Sync on Role Change

`App\Services\UserHomeResolver::resolve()` = single source of truth for role-based post-login and guest-guard redirects. New role in `RolesEnum` needing its own dashboard **must** update this method, else it falls through to the `student.courses.index` catch-all. Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` delegate here, so one edit covers both paths.

After editing:
```bash
vendor/bin/sail artisan test --filter=LoginTest
```
`tests/Feature/Auth/LoginTest.php` asserts role-specific redirect targets and catches a missed update.

## Login Screen Selector/Copy Contract Lives in the Dusk `LoginTest`

`tests/Browser/Auth/LoginTest.php` additionally guards the *screen*, not just the redirect:

- `test_login_screen_exposes_the_selector_contract_and_offers_no_signup_path` — all seven `dusk="login-*"`/`password-toggle`/`forgot-password-link` hooks, the input `type`s, the forgot-password `href`, and the **absence** of any `/register` link or "Criar conta"/"Cadastre-se" copy (see `auth-orgs-conventions`, guest shell markup contract).
- `test_login_credential_rejections` — a wrong password **and** a non-existent e-mail must produce the *same* generic message; a failure here usually means someone "improved" the copy into an account-enumeration oracle.

Failing after a Blade edit? Rebuild first (`vendor/bin/sail npm run build`), and check the selector was not moved onto a wrapper element by a component swap — `DuskSelectorContractTest` (PHPUnit) catches the moved/dropped case faster than the browser run.

## Auto-Update Protocol

Any change to `UserController`, `Admin\UserAdminController`, `UserImportController`, `UserImportService`, `ProvisionOrgAccountAction`, `OrgCredentialUserProvider`, `UserPolicy`, `UpdateUserAdminRequest`, `LoginRequest`/auth controllers, `users*`/`admin.users.*` routes, `CsvImporter.js`, or the guest-shell auth views (`resources/views/auth/login.blade.php`, `resources/views/layouts/guest.blade.php`, `resources/views/components/layout/guest-panel.blade.php`) **must** update all three auth-orgs skills (`auth-orgs-architecture`, `auth-orgs-conventions`, `auth-orgs-maintenance`) in the same change before the task is done. Also:

- `.agents/agents/code-reviewer.md` — if the change alters what a reviewer checks for this module.
- `vendor/bin/sail artisan harness:check-skills` — fails the build if any `auth-orgs-*` skill is missing.

## Related

- `tenancy-maintenance` — underlying `OrgScope`/`RolesEnum`/host-resolution contract.

---

## E2E Coverage Lives in Lifecycle Chains

`tests/Browser/` groups by **user journey (lifecycle chain)** — one method drives create, edit, state change, delete, consequence. Not by module or feature.

- **Find coverage**: chain methods may sit in a file named after another module when the journey crosses boundaries. Search `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name. Missing per-module file is **not** a gap.
- **Add coverage**: extend the journey's chain with a numbered step carrying UI **and** DB assertion. New method only for independent negatives (403, cross-tenant, other actor). New file only for genuinely new journey.
- **Debug**: stack trace points at a step — match line to its `// N.` comment. Late failure usually means earlier step did not persist.
- **Database**: no DB trait in `tests/Browser/*`; `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding `DatabaseMigrations` = suite-wide slowdown. Files, cache, session not reset between methods.

Full rule: `testing-conventions`. Chain debug: `testing-maintenance`.
