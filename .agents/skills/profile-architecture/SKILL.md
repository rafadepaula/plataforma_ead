---
name: profile-architecture
description: >
  Explains the User Profile Self-Service domain (SPEC-18/UC02): why
  `ProfileController`/`PasswordController` act exclusively on
  `$request->user()` with no `{user}` route parameter and no Policy, how
  `org_id`/`status` stay immutable through this endpoint even under
  Impersonate Org (RN12), the `Auth::logoutOtherDevices()` session-
  invalidation contract, and why `App\Rules\Cpf` is a shared, uniformly
  applied Rule rather than a profile-only validation. Use whenever
  designing or reviewing a feature that touches self-service profile or
  password data, before adding a new profile field, or when deciding how
  a CPF-bearing form should validate.
license: MIT
metadata:
  feature: profile
  role: architecture
  specs:
    - spec/specs/18-user-profile-management.md
    - spec/docs/usecases/UC02-gestao-de-perfil-do-usuario.md
---

# Profile Architecture

## Overview

UC02 closes a gap that existed since the project's first auth work: an
authenticated user (any role) had no way to change their own name,
e-mail, CPF, or password — only a Gestor/Admin editing them through
`UserController` (RF04) could, which is unacceptable for a password.
SPEC-18 adds two controllers, two Form Requests, one shared validation
Rule, and a two-block Blade screen. No migration — every column already
exists on `users`.

## Identity-Scoped, Not Tenant-Scoped

`User` deliberately does **not** use `OrgScope` (see its own docblock).
Every other CRUD in this app protects against a *cross-tenant* actor;
this feature protects against a *cross-identity* actor, and the fix is
structural rather than a runtime check: there is no `{user}` route
parameter anywhere in the `profile.*`/`password.update` routes, so the
only row either controller can ever touch is `$request->user()`. No
Policy exists or is needed — there is nothing to authorize a route
parameter against.

```php
// ProfileController — always $request->user(), never route-model-bound
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $request->user()->update($request->only(['name', 'email', 'cpf']));
    ...
}
```

## `org_id` and `status` Are Immutable Through This Endpoint

Neither `ProfileUpdateRequest` nor `PasswordUpdateRequest` declares an
`org_id` or `status` rule, and the controllers only ever pass an explicit
allow-list (`only(['name', 'email', 'cpf'])`) to `update()` — the same
defensive pattern `UpdateUserRequest`/`UserController` use. This matters
under RN12 (Impersonate Org): an Admin impersonating an Org and visiting
`/profile` is editing their own **global** Admin row, never the
impersonated Org's data — the controller never reads or writes
`org_id`/`session('active_org_id')` at all, so impersonation state is
simply irrelevant to this feature, not something it has to defend
against explicitly.

## Password Change Invalidates Other Sessions, Not the Current One

`PasswordController::update()` calls
`Auth::logoutOtherDevices($plainCurrentPassword)` **before** rotating the
password (see `profile-conventions` for why the ordering is
load-bearing). This relies on `SESSION_DRIVER=database`, already
configured project-wide, to actually revoke other sessions' tokens. The
route carries `throttle:6,1` specifically so that `current_password`
(validated via Laravel's native `current_password` rule) cannot be used
as a brute-force oracle against the live session's password.

## `App\Rules\Cpf` Is a Shared Primitive, Not a Profile-Only Rule

RN17 requires uniform CPF checksum validation everywhere CPF is
accepted, not just here. `App\Rules\Cpf` is a pure, DB-free
`ValidationRule` (mod-11 checksum + identical-digit-sequence rejection)
reused by `ProfileUpdateRequest`, `StoreUserRequest`, `UpdateUserRequest`,
and `ProcessInvitationRequest`. `ImportUsersChunkRequest` is the one
deliberate exception: a CSV row with an invalid CPF must be skipped by
`UserImportService` with a recorded reason, never abort the whole
50-record chunk with a 422 — see `auth-orgs-maintenance` for the import
pipeline. Never add a second CPF-checksum implementation; every new
CPF-accepting entry point should add `new Cpf` to its rule array instead.

## `email_verified_at` Is Never Reset

The project has no `MustVerifyEmail` flow in place (commented out on
`User`) and no verification-link infrastructure anywhere. Changing
`email` here deliberately leaves `email_verified_at` untouched — wiring
up verification only for this one entry point would create an orphaned,
half-built flow. If e-mail re-verification is ever wanted, it is a new
use case, not an extension of this one.
