---
name: profile-architecture
description: >
  User Profile Self-Service domain: why
  `ProfileController` acts only on `$request->user()` (identity:
  name/email/cpf), no `{user}` route parameter, no Policy; why
  `PasswordController` rotates the **host org's `credentials`** row
  (password + `remember_token`) and never a global column.
  `AuthenticateSession` invalidates stale sessions after the change. Why
  `App\Rules\Cpf` is shared, uniformly applied Rule, not profile-only
  validation. Use when designing or reviewing feature touching self-service
  profile or password data, before adding new profile field, or when
  deciding how CPF-bearing form validates.
license: MIT
metadata:
  feature: profile
  role: architecture
---

# Profile Architecture

## Overview

Authenticated user (any role) manages own identity — name, e-mail, CPF —
and own **portal password** here, without going through
`UserController`. Post host-tenancy, the two concerns live on different
tables: identity columns on `users` (the global person row),
account data (password, status, remember token) on the per-org
`credentials` row. `ProfileController` writes only `users`;
`PasswordController` writes only the request host's `Credential`. Two
controllers, two Form Requests, one shared validation Rule, two-block
Blade screen.

## Identity-Scoped, Not Tenant-Scoped

`User` deliberately does **not** use `OrgScope` (see its own docblock).
Every other CRUD in app protects against *cross-tenant* actor. This feature
protects against *cross-identity* actor, and fix is structural, not runtime
check: no `{user}` route parameter anywhere in `profile.*`/`password.update`
routes, so only row either controller can touch is `$request->user()`
(`ProfileController`) or `$request->user()->credentialFor(OrgContext::current()->organization)`
(`PasswordController`). No Policy exists or needed. Nothing to authorize a
route parameter against.

```php
// ProfileController — always $request->user(), never route-model-bound
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $request->user()->update($request->only(['name', 'email', 'cpf']));
    ...
}
```

## Account Columns Are Off-Limits To Request Input

`ProfileUpdateRequest` validates only `name`/`email`/`cpf` and the
controller passes an explicit allow-list to `update()`. Neither request
declares an `org_id`/`status` rule — those columns belong to
`credentials`, and the only credential write in this feature is
`PasswordController`'s password + `remember_token` rotation on the host
org's row. Status is read-only everywhere here: `profile/edit.blade.php`
renders it via `$user->account_status` (accessor over the host org's
credential), never `$user->status` — that column no longer exists.
Matters under Impersonate Org: Admin impersonating Org and visiting
`/profile` edits own **global** identity, never impersonated Org data.

## Password Change Writes The Host Org's Credential And Rotates `remember_token`

`PasswordController::update()` resolves
`$request->user()->credentialFor(OrgContext::current()->organization)`
(403 when the person holds no account in this portal), then `forceFill`s
the new hash **and** a fresh `remember_token` on that credential — the
`logoutOtherDevices` equivalent for org-scoped credentials (no
`Auth::logoutOtherDevices()` call: there is no global `users.password`
for it to check anymore). Rotation invalidates every remember-me cookie
issued by this portal; every *other* active session is cut by
`AuthenticateSession` (`auth.session` middleware, `web` group): its
password fingerprint no longer matches the rotated credential hash, so
the stale session is logged out on its next request — exactly what
`PasswordUpdateTest::test_changing_password_logs_out_other_active_sessions`
drives through the real middleware. Only this portal's credential
changes; the person's accounts in other Organizations keep their own
passwords. Route carries `throttle:6,1` so `current_password` cannot
serve as brute-force oracle. `current_password` validates via Laravel's
native rule against `User::getAuthPassword()` — which returns the host
org credential's hash (`credentialFor(OrgContext::current()->organization)?->password`).

## `App\Rules\Cpf` Is Shared Primitive, Not Profile-Only Rule

CPF checksum validation must be uniform everywhere CPF accepted, not
just here. `App\Rules\Cpf` is pure, DB-free `ValidationRule` (mod-11
checksum + identical-digit-sequence rejection) reused by 9 CPF-accepting
entry points: `ProfileUpdateRequest`, `StoreUserRequest`,
`UpdateUserRequest`, `UpdateUserAdminRequest`,
`StoreGestorProfessorRequest`, `UpdateGestorStudentRequest`,
`UpdateGestorProfessorRequest`, `StoreStudentEnrollmentRequest`,
`ProcessInvitationRequest`. `ImportUsersChunkRequest` is one deliberate
exception: CSV row with invalid CPF must be skipped by `UserImportService`
with recorded reason, never abort whole 50-record chunk with 422. See
`auth-orgs-maintenance` for import pipeline. Never add second CPF-checksum
implementation. Every new CPF-accepting entry point adds `new Cpf` to its
rule array instead.

## `email_verified_at` Never Reset

Project has no `MustVerifyEmail` flow (commented out on `User`) and no
verification-link infrastructure anywhere. Changing `email` here
deliberately leaves `email_verified_at` untouched. Wiring verification only
for this one entry point would create orphaned, half-built flow. If e-mail
re-verification ever wanted, it is a new feature, not extension of this one.
