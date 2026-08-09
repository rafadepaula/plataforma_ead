---
name: profile-maintenance
description: >
  Debugging, testing, and edge-case guide for the User Profile
  Self-Service feature (SPEC-18/UC02): the mandatory PHPUnit/Dusk test
  files (including the CPF-checksum browser scenario), common
  `current_password`/`logoutOtherDevices()`/uniqueness failure modes, and
  the `App\Rules\Cpf` regression surface now that it is wired into a live
  Blade form. Use when `ProfileTest`, `PasswordUpdateTest`, `CpfTest`, or
  `tests/Browser/ProfileTest.php` is failing, a profile update silently
  no-ops, or a password change doesn't revoke other sessions.
license: MIT
metadata:
  feature: profile
  role: maintenance
  specs:
    - spec/specs/18-user-profile-management.md
    - spec/docs/usecases/UC02-gestao-de-perfil-do-usuario.md
---

# Profile Maintenance

## Mandatory Test Coverage for This Module

- `tests/Unit/Rules/CpfTest.php` — the checksum algorithm in isolation:
  valid CPFs, wrong-length input, identical-digit-sequence rejection
  (`00000000000`…`99999999999`), each of the two check digits
  individually broken, punctuation-stripping normalization.
- `tests/Feature/ProfileTest.php` — `ProfileController`/`ProfileUpdateRequest`:
  successful name/email/cpf update, duplicate-email rejection, duplicate-
  CPF rejection, invalid-checksum CPF rejection, `org_id`/`status` never
  change even if injected in the request payload, guest redirected to
  `/login`.
- `tests/Feature/PasswordUpdateTest.php` — `PasswordController`/
  `PasswordUpdateRequest`: successful change + `logoutOtherDevices()`
  actually invalidates another session row, wrong `current_password`
  rejected and password unchanged, `Password::defaults()` policy
  enforced, `throttle:6,1` triggers a 429 on the 7th attempt within a
  minute.
- `tests/Browser/ProfileTest.php` (Dusk E2E) — all 5 UC02 scenarios: edit
  data successfully, change password successfully, duplicate email/CPF
  rejected inline without redirecting away, wrong `current_password`
  rejected inline, guest redirected to `/login`. **Includes a
  checksum-invalid-CPF scenario** distinct from the duplicate-CPF one —
  UC02 §6.2 is its own exception flow (*"O CPF informado é inválido."*)
  and must not be conflated with §6.1's duplicate-value flow, which
  exercises a different rule (`unique`) entirely.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=CpfTest
vendor/bin/sail artisan test --filter=ProfileTest
vendor/bin/sail artisan test --filter=PasswordUpdateTest
vendor/bin/sail dusk --filter=ProfileTest
```

Dusk tests use `DatabaseMigrations`, never `RefreshDatabase` — see
`laravel-dusk`.

## Common Failure Modes

- **A profile update silently no-ops.** `ProfileController::update()`
  uses `$request->only(['name', 'email', 'cpf'])` — if a new field is
  added to the Blade form and `ProfileUpdateRequest`'s rules but the
  controller's `only()` allow-list isn't updated too, the field validates
  fine and is silently dropped before `update()`. Always update both
  together.
- **Invalid-checksum CPF is accepted by the live form.** This is the gap
  `tests/Browser/ProfileTest.php`'s checksum scenario guards: a
  regression that drops `new Cpf` from `ProfileUpdateRequest::rules()`,
  or a Blade `name="cpf"`/`dusk` mismatch that makes Dusk type into the
  wrong field, passes every PHPUnit test (the Rule itself is covered in
  isolation by `CpfTest`, and Feature-level `ProfileTest` posts directly
  to the route bypassing the browser form) while the real screen accepts
  garbage. If this browser test starts failing, check
  `ProfileUpdateRequest::rules()`'s `cpf` array first, then the `cpf`
  input's `name`/`dusk` attributes in `profile/edit.blade.php`.
- **Password change doesn't revoke the other session.** Verify
  `SESSION_DRIVER=database` in the running environment (it degrades
  silently with `file`/`array` drivers — `logoutOtherDevices()` still
  "succeeds" but there is no cross-session row to invalidate). Also
  confirm the call happens *before* `Hash::make()` — see
  `profile-conventions`'s ordering note; the reordered version fails
  `logoutOtherDevices()`'s internal password check against the
  already-rotated hash and throws, which a broad try/catch elsewhere in
  the stack could mask as "did nothing" rather than a visible error.
- **`current_password` rule accepted on a JSON/API request context.**
  Laravel's native `current_password` rule checks against the
  *authenticated guard's* stored hash for the request's resolved guard —
  if this feature is ever exposed over `api`/Sanctum with a different
  guard than `web`, re-verify the rule still targets the right guard
  explicitly (`current_password:api`) rather than assuming the default.
  Not currently an issue (this feature is `web`-only), but a fast trap if
  it's ever extended.
- **Duplicate email/CPF test flakes with `assertPathIs('/profile')`
  after `->waitForReload()`.** A validation failure is a `back()`
  redirect, which lands back on `/profile` (a 302, not a 422) — if this
  assertion ever fails intermittently, it is almost always a
  `waitForReload()` timing issue (see `laravel-dusk`), not an actual
  validation regression.

## `App\Rules\Cpf` Regression Surface

Because `Cpf` is shared across `ProfileUpdateRequest`, `StoreUserRequest`,
`UpdateUserRequest`, and `ProcessInvitationRequest`, a change to its
algorithm affects all four call sites simultaneously — run
`CpfTest` plus every Feature test in `auth-orgs-maintenance`'s coverage
list (`UserCrudTest`, the invitation acceptance tests) after touching
`app/Rules/Cpf.php`, not just this module's own suite.
