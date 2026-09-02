---
name: profile-architecture
description: >
  User Profile Self-Service domain: why
  `ProfileController`/`PasswordController` act only on `$request->user()`,
  no `{user}` route parameter, no Policy. How `org_id`/`status` stay
  immutable through this endpoint even under Impersonate Org.
  `Auth::logoutOtherDevices()` session-invalidation contract. Why
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

This feature closes the gap open since project first auth work: authenticated user (any
role) had no way to change own name, e-mail, CPF, or password. Only
Gestor/Admin editing them through `UserController` could, which is
unacceptable for password. The feature adds two controllers, two Form Requests,
one shared validation Rule, two-block Blade screen. No migration. Every
column already exists on `users`.

## Identity-Scoped, Not Tenant-Scoped

`User` deliberately does **not** use `OrgScope` (see its own docblock).
Every other CRUD in app protects against *cross-tenant* actor. This feature
protects against *cross-identity* actor, and fix is structural, not runtime
check: no `{user}` route parameter anywhere in `profile.*`/`password.update`
routes, so only row either controller can touch is `$request->user()`. No
Policy exists or needed. Nothing to authorize a route parameter against.

```php
// ProfileController — always $request->user(), never route-model-bound
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $request->user()->update($request->only(['name', 'email', 'cpf']));
    ...
}
```

## `org_id` and `status` Immutable Through This Endpoint

Neither `ProfileUpdateRequest` nor `PasswordUpdateRequest` declares `org_id`
or `status` rule, and controllers only ever pass explicit allow-list
(`only(['name', 'email', 'cpf'])`) to `update()`. Same defensive pattern
`UpdateUserRequest`/`UserController` use. Matters under Impersonate
Org: Admin impersonating Org and visiting `/profile` edits own **global**
Admin row, never impersonated Org data. Controller never reads or writes
`org_id`/`session('active_org_id')` at all, so impersonation state is
irrelevant to this feature, not something it defends against explicitly.

## Password Change Invalidates Other Sessions, Not Current One

`PasswordController::update()` calls
`Auth::logoutOtherDevices($plainCurrentPassword)` **before** rotating
password (see `profile-conventions` for why ordering is load-bearing).
Relies on `SESSION_DRIVER=database`, already configured project-wide, to
actually revoke other session tokens. Route carries `throttle:6,1`
specifically so `current_password` (validated via Laravel native
`current_password` rule) cannot serve as brute-force oracle against live
session password.

## `App\Rules\Cpf` Is Shared Primitive, Not Profile-Only Rule

CPF checksum validation must be uniform everywhere CPF accepted, not
just here. `App\Rules\Cpf` is pure, DB-free `ValidationRule` (mod-11
checksum + identical-digit-sequence rejection) reused by
`ProfileUpdateRequest`, `StoreUserRequest`, `UpdateUserRequest`,
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
