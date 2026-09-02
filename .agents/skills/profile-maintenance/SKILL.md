---
name: profile-maintenance
description: >
  Debug, test, edge-case guide for User Profile Self-Service:
  mandatory PHPUnit/Dusk test files (including
  CPF-checksum browser scenario), common
  `current_password`/`logoutOtherDevices()`/uniqueness failure modes,
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
  `org_id`/`status` never change even if injected in request payload,
  guest redirected to `/login`.
- `tests/Feature/PasswordUpdateTest.php` — `PasswordController`/
  `PasswordUpdateRequest`: successful change + `logoutOtherDevices()`
  actually invalidate another session row, wrong `current_password`
  rejected and password unchanged, `Password::defaults()` policy
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
- **Password change not revoke other session.** Verify
  `SESSION_DRIVER=database` in running environment (degrade silently with
  `file`/`array` drivers — `logoutOtherDevices()` still "succeed" but no
  cross-session row to invalidate). Also confirm call happen *before*
  `Hash::make()` — see `profile-conventions` ordering note; reordered
  version fail `logoutOtherDevices()` internal password check against
  already-rotated hash and throw, which broad try/catch elsewhere in
  stack could mask as "did nothing" rather than visible error.
- **`current_password` rule accepted on JSON/API request context.**
  Laravel native `current_password` rule check against *authenticated
  guard* stored hash for request resolved guard — if this feature ever
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

`Cpf` shared across `ProfileUpdateRequest`, `StoreUserRequest`,
`UpdateUserRequest`, `ProcessInvitationRequest` — change to its algorithm
affect all four call sites simultaneously. Run `CpfTest` plus every
Feature test in `auth-orgs-maintenance` coverage list (`UserCrudTest`,
invitation acceptance tests) after touch `app/Rules/Cpf.php`, not just
this module own suite.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
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
