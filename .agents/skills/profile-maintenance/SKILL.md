---
name: profile-maintenance
description: >
  User Profile Self-Service: `ProfileController` acting only on `$request->user()`,
  `PasswordController` rotating the host org `credentials` row (password +
  `remember_token`) with `AuthenticateSession` invalidation, two-independent-forms
  Blade pattern, shared `App\Rules\Cpf`. Use when designing or reviewing self-service
  profile/password data, before adding a new profile field, when deciding how a
  CPF-bearing form validates, when writing controller/Form Request/Blade/test touching
  `ProfileController`/`PasswordController`/`App\Rules\Cpf`, or when `ProfileTest`,
  `PasswordUpdateTest`, `CpfTest` or `tests/Browser/ProfileTest.php` fails, a profile
  update silently no-ops, or a password change does not revoke other sessions.
license: MIT
metadata:
  feature: profile
  roles: [architecture, conventions, maintenance]
---

# User Profile Self-Service (`profile-maintenance`)

User Profile Self-Service: `ProfileController` acts only on `$request->user()` (no `{user}` parameter, no Policy), `PasswordController` rotates the host org's `credentials` row (password + `remember_token`), `AuthenticateSession` invalidates stale sessions, shared `App\Rules\Cpf`.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching self-service profile or password data, before adding a new profile field, or when deciding how a CPF-bearing form validates. |
| `resource/conventions.md` | Writing controller, Form Request, Blade view, or test touching `ProfileController`, `PasswordController`, or `App\Rules\Cpf`. |
| `resource/maintenance.md` | `ProfileTest`, `PasswordUpdateTest`, `CpfTest`, or `tests/Browser/ProfileTest.php` fails; profile update silently no-op; password change not revoking other sessions. |
