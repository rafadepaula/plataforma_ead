---
name: auth-orgs-architecture
description: >
  Explains the RF01/RF02 authentication architecture (login, role-based
  redirect, single-use password-reset token flow) and how it plugs into the
  pre-existing multitenancy architecture (Organizations, `OrgScope`,
  Impersonate Org, `RolesEnum`). Use whenever you need to understand how a
  user authenticates, why `status=active` gates login, how post-login
  redirects are resolved per role, or how the password-reset e-mail is
  delivered, before touching auth routes/controllers/views or anything that
  reads `Auth::user()`/`session('active_org_id')` at login/logout time.
license: MIT
metadata:
  feature: auth-orgs
  role: architecture
  specs:
    - spec/specs/04-auth-profile-organizations-and-user-management.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Auth + Orgs Architecture

## Overview

SPEC-04 RF01/RF02 implements a **custom** (no Breeze/Fortify/Jetstream)
session-based auth flow on top of the `users` table and `RolesEnum`/`OrgScope`
tenancy model that SPEC-00 already established (see `tenancy-architecture`).
No new dependency, no new migration: authentication only *reads* the
pre-existing `email`, `password`, `status`, `org_id` columns and Spatie roles.

## Why Custom Controllers, Not a Starter Kit

`composer.json` confirms no `laravel/breeze`/`laravel/fortify`/`laravel/
jetstream` package is installed, and CLAUDE.md forbids changing dependencies
without approval. RF01/RF02's scope (email+password login, role check,
single-use reset token) is small enough that hand-written controllers mirror
Breeze's well-known pattern (`LoginRequest::authenticate()`,
`Password::sendResetLink()`/`Password::reset()`) without pulling in a
scaffolding package or its opinionated view stack.

## Request Flow

```
GET  /login                     -> AuthenticatedSessionController::create
POST /login                     -> AuthenticatedSessionController::store   (LoginRequest::authenticate())
POST /logout                    -> AuthenticatedSessionController::destroy  (auth-gated)
GET  /forgot-password           -> PasswordResetLinkController::create
POST /forgot-password           -> PasswordResetLinkController::store      (Password::sendResetLink())
GET  /reset-password/{token}    -> NewPasswordController::create
POST /reset-password            -> NewPasswordController::store            (Password::reset())
```

All six routes live in `routes/auth.php`, required from `routes/web.php`
(kept out of `routes/web.php` itself so the auth surface stays reviewable in
one file). The `login`/`forgot-password`/`reset-password/{token}` GET routes
sit behind Laravel's built-in `guest` middleware alias; `logout` sits behind
`auth`. Both aliases are framework defaults — `bootstrap/app.php`'s
`$middleware->alias([...])` call only *adds* the Spatie role aliases, it does
not replace the framework's own (`auth`, `guest`, ...).

## `status=active` Gate (RF01)

`User::status` (`active`/`inactive`, migration default `active`) is folded
into the credentials array passed to `Auth::attempt()`:

```php
Auth::attempt($this->only('email', 'password') + ['status' => 'active'], ...)
```

`EloquentUserProvider::retrieveByCredentials()` turns every non-`password`
key into a `WHERE` clause, so an `inactive` user simply fails the user
*lookup* — there is no separate "logged in, then kicked out" branch to get
wrong, and no timing difference between "wrong password" and "inactive
account" that would leak account status to a prober. Both surface as the
same generic `auth.failed` validation error.

## Rate Limiting

`LoginRequest` throttles by `lower(email)|ip` (5 attempts) via
`RateLimiter`/`Illuminate\Auth\Events\Lockout`, the same mechanism Breeze
ships — not a new package, just the facade already bundled with the
framework. Cleared on a successful attempt.

## Post-Login Redirect (Role-Based)

`AuthenticatedSessionController::redirectPathFor()` sends `admin`/`gestor` to
`admin.dashboard` and everyone else (`aluno`) to `student.courses.index` —
**if those named routes exist**. Later SPEC-04/other-spec buckets own those
dashboards; until they land, every role safely falls back to `/`
(`Route::has()` guard, mirrors the same pattern already used in
`resources/views/components/layout/{topbar,sidebar}.blade.php`). Do not hardcode
a URL here — always add a new role destination behind a `Route::has()` check
so this controller keeps working before its downstream route exists.

`redirect()->intended()` is used so a guest bounced to `/login` by the
`auth` middleware (or by the `UnauthorizedException` guest-redirect in
`bootstrap/app.php`) returns to the page they originally asked for, instead
of always landing on the role's default destination.

## Password Reset (RF02) — Single-Use Token via SMTP

Uses Laravel's built-in password-broker (`Illuminate\Auth\Passwords`), not a
custom token table — `password_reset_tokens` already existed from the base
`users` migration (SPEC-00 didn't touch it). `Password::sendResetLink()` /
`Password::reset()` handle hashing, expiry (`config('auth.passwords.users.
expire')`, 60 minutes) and **single-use deletion** of the token row on
success — a second `POST /reset-password` with the same token always fails
with `passwords.token` (mapped to the `email` field in the response).

**Delivery**: `App\Notifications\ResetPasswordNotification` extends
`Illuminate\Auth\Notifications\ResetPassword` to localize the e-mail copy
(pt-BR) and reuses the parent's `resetUrl()` (which itself resolves via the
`password.reset` named route — no manual URL building). `User::
sendPasswordResetNotification()` is overridden to dispatch this class instead
of the framework default. The mailer is whatever `MAIL_MAILER`/`config(
'mail.php')` resolves to (SMTP in production, `log` in local `.env`, `array`
in tests via `phpunit.xml`) — there is no auth-specific mail configuration,
it is the app's one mailer config.

## Testing Notes

- `Auth::attempt()`'s `status => 'active'` trick means an inactive-user test
  only needs `User::factory()->inactive()->create(...)` (see `UserFactory`)
  plus a normal login POST — no separate code path to stub.
- Password-reset tests use `Notification::fake()` +
  `Notification::assertSentTo($user, ResetPasswordNotification::class, fn
  ($n) => ...)` to pull the real `$notification->token` out of the faked
  notification and drive the reset form with it (see
  `tests/Feature/Auth/PasswordResetTest.php`) — never assert against a
  hardcoded/fake token string.
- Dusk (`tests/Browser/Auth/LoginTest.php`) uses `DatabaseMigrations` (never
  `RefreshDatabase` — Dusk drives the app as a separate HTTP process) and
  targets the `dusk="login-*"` attributes rendered on the Blade form, not CSS
  classes.

## Related Skills / Specs

- `tenancy-architecture` — `RolesEnum`, `OrgScope`, Impersonate Org; this
  skill assumes that one and only documents what RF01/RF02 adds on top.
- `auth-orgs-conventions` — controller/policy/form-request conventions
  shared by Org CRUD, User CRUD and CSV Import (SPEC-04 Buckets B/C).
- `auth-orgs-maintenance` — RN09 edge cases and the module's
  coverage/maintenance checklist.
- `spec/specs/04-auth-profile-organizations-and-user-management.md` — RF01/
  RF02/RF23/RF04/RF05 requirements this module implements.
