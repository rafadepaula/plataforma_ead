---
name: profile-maintenance
description: >
  Debug, test, edge-case guide for User Profile Self-Service:
  mandatory PHPUnit/Dusk test files (including
  CPF-checksum browser scenario), common
  `current_password`/credential-targeting/session-invalidation/uniqueness
  failure modes,
  `App\Rules\Cpf` regression surface now wired into live Blade form. Use
  when `ProfileTest`, `PasswordUpdateTest`, `CpfTest`, or
  `tests/Browser/ProfileTest.php` fail, profile update silently no-op, or
  password change not revoke other sessions.
license: MIT
metadata:
  feature: profile
  role: maintenance
---

# Profile Maintenance

## Mandatory Test Coverage for This Module

- `tests/Unit/Rules/CpfTest.php` — checksum algorithm in isolation: valid
  CPFs, wrong-length input, identical-digit-sequence rejection
  (`00000000000`…`99999999999`), each of two check digits individually
  broken, punctuation-stripping normalization.
- `tests/Feature/ProfileTest.php` — `ProfileController`/`ProfileUpdateRequest`:
  successful name/email/cpf update, duplicate-email rejection,
  duplicate-CPF rejection, invalid-checksum CPF rejection,
  `org_id`/`status` never mutate even if injected in request payload
  (asserted through `credentialFor()` states — the columns live on
  `credentials` now),
  guest redirected to `/login`.
- `tests/Feature/PasswordUpdateTest.php` — `PasswordController`/
  `PasswordUpdateRequest`: successful change writes the **host org's
  credential** (old password stops authenticating, new one starts),
  wrong `current_password` rejected and password unchanged,
  `test_changing_password_logs_out_other_active_sessions` drives the
  real `auth.session` (`AuthenticateSession`) middleware so device B's
  stale session dies on its next request (not `actingAs()` — never
  simplify that), `Password::defaults()` policy
  enforced, `throttle:6,1` trigger 429 on 7th attempt within minute.
- `tests/Browser/ProfileTest.php` (Dusk E2E) — all 5 scenarios: edit
  data successfully, change password successfully, duplicate email/CPF
  rejected inline without redirecting away, wrong `current_password`
  rejected inline, guest redirected to `/login`. **Includes
  checksum-invalid-CPF scenario** distinct from duplicate-CPF one —
  *"O CPF informado é inválido."* is its own exception flow, must not
  be conflated with the duplicate-value flow, which exercises the different
  rule (`unique`) entirely.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=CpfTest
vendor/bin/sail artisan test --filter=ProfileTest
vendor/bin/sail artisan test --filter=PasswordUpdateTest
vendor/bin/sail dusk --filter=ProfileTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk run in separate
HTTP process); `DatabaseMigrations` retired (per-method `migrate:fresh`)
— see `laravel-dusk`/`testing-conventions`.

## Common Failure Modes

- **Profile update silently no-op.** `ProfileController::update()` use
  `$request->only(['name', 'email', 'cpf'])` — if new field added to
  Blade form and `ProfileUpdateRequest` rules but controller `only()`
  allow-list not updated too, field validate fine and get silently
  dropped before `update()`. Always update both together.
- **Invalid-checksum CPF accepted by live form.** This is gap
  `tests/Browser/ProfileTest.php` checksum scenario guard: regression
  that drop `new Cpf` from `ProfileUpdateRequest::rules()`, or Blade
  `name="cpf"`/`dusk` mismatch making Dusk type into wrong field, pass
  every PHPUnit test (Rule itself covered in isolation by `CpfTest`, and
  Feature-level `ProfileTest` post directly to route bypassing browser
  form) while real screen accept garbage. If this browser test start
  failing, check `ProfileUpdateRequest::rules()` `cpf` array first, then
  `cpf` input `name`/`dusk` attributes in `profile/edit.blade.php`.
- **Password change not revoke other session.** Invalidations now come
  from two places — `remember_token` rotation on the credential (kills
  remember-me cookies) and `AuthenticateSession` (`auth.session`
  middleware, `web` group in `bootstrap/app.php`) fingerprinting the
  credential hash (kills stale session rows on their next request).
  Check, in order: middleware still registered on the `web` group;
  `PasswordController` still rotating `remember_token` alongside the
  hash; `SESSION_DRIVER=database` in the running environment (with
  `file`/`array` drivers another device's session may not even survive
  to be invalidated — test against `database`); and that the change
  wrote to the **credential** the user actually authenticated with (the
  host org's row), not some other org's row.
- **Password change hits the wrong portal's account.** Symptom: person's
  password changes in org A while they were on org B's host (or worse, a
  credential is created where none should be). The controller must
  resolve the target with
  `$request->user()->credentialFor(OrgContext::current()->organization)`
  and `abort(403)` when it comes back `null` — never
  `firstOrCreate`, never `User::update(['password' => ...])`.
- **`current_password` rule accepted on JSON/API request context.**
  Laravel native `current_password` rule check against *authenticated
  guard* stored hash — which here means `User::getAuthPassword()`, i.e.
  the host org credential's hash. If this feature ever
  exposed over `api`/Sanctum with different guard than `web`, re-verify
  rule still target right guard explicitly (`current_password:api`)
  rather than assume default. Not currently issue (feature is `web`-only),
  but fast trap if extended.
- **Duplicate email/CPF test flake with `assertPathIs('/profile')` after
  `->waitForReload()`.** Validation failure is `back()` redirect, land
  back on `/profile` (302, not 422) — if this assertion ever fail
  intermittently, almost always `waitForReload()` timing issue (see
  `laravel-dusk`), not actual validation regression.

## `App\Rules\Cpf` Regression Surface

`Cpf` shared across 9 entry points (`ProfileUpdateRequest`,
`StoreUserRequest`, `UpdateUserRequest`, `UpdateUserAdminRequest`,
`StoreGestorProfessorRequest`, `UpdateGestorStudentRequest`,
`UpdateGestorProfessorRequest`, `StoreStudentEnrollmentRequest`,
`ProcessInvitationRequest`) — change to its algorithm
affect all nine call sites simultaneously. Run `CpfTest` plus every
Feature test in `auth-orgs-maintenance` coverage list (`UserCrudTest`,
invitation acceptance tests) after touch `app/Rules/Cpf.php`, not just
this module own suite.

---

## E2E Coverage: Per-Module File Exists

This module HAS its own Dusk file — `tests/Browser/ProfileTest.php`
(5 scenarios listed above, including the checksum-invalid-CPF one).
Prefer it over chain-grep when maintaining this module.

Browser tests in `tests/Browser/` are otherwise grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
maintain this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
