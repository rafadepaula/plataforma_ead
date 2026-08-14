---
name: auth-orgs-architecture
description: >
  RF01/RF02 auth architecture (login, role-based redirect, single-use
  password-reset token) and how it plugs into existing multitenancy
  (Organizations, `OrgScope`, Impersonate Org, `RolesEnum`). Use when you
  need how a user authenticates, why `status=active` gates login, how
  post-login redirect resolves per role, or how reset e-mail is delivered,
  before touching auth routes/controllers/views or code reading
  `Auth::user()`/`session('active_org_id')` at login/logout.
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

SPEC-04 RF01/RF02 = **custom** session auth (no Breeze/Fortify/Jetstream) on top of `users` table and the `RolesEnum`/`OrgScope` tenancy from SPEC-00 (see `tenancy-architecture`). No new dependency, no new migration. Auth only *reads* existing `email`, `password`, `status`, `org_id` columns and Spatie roles.

## Why Custom Controllers

`composer.json` has no `laravel/breeze`/`fortify`/`jetstream`, and CLAUDE.md forbids changing dependencies without approval. RF01/RF02 scope (email+password login, role check, single-use reset token) is small, so hand-written controllers mirror Breeze's pattern (`LoginRequest::authenticate()`, `Password::sendResetLink()`/`Password::reset()`) without a scaffolding package or its view stack.

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

All six in `routes/auth.php`, required from `routes/web.php` — keeps the auth surface reviewable in one file. `login`/`forgot-password`/`reset-password/{token}` GET behind `guest` alias; `logout` behind `auth`. `auth` alias stays framework default. `guest` is **overridden** in `bootstrap/app.php` (BUG-001 fix) to `App\Http\Middleware\RedirectIfAuthenticated`, which gives role-aware targets via `UserHomeResolver` instead of the framework fallback to `/`.

## `status=active` Gate (RF01)

`User::status` (`active`/`inactive`, migration default `active`) folded into credentials:

```php
Auth::attempt($this->only('email', 'password') + ['status' => 'active'], ...)
```

`EloquentUserProvider::retrieveByCredentials()` turns every non-`password` key into a `WHERE`, so an `inactive` user fails the *lookup*. No "logged in then kicked out" branch to get wrong, no timing difference between wrong password and inactive account leaking status to a prober. Both give the same generic `auth.failed` error.

## Rate Limiting

`LoginRequest` throttles on `lower(email)|ip`, 5 attempts, via `RateLimiter`/`Illuminate\Auth\Events\Lockout` — same mechanism Breeze ships, facade already bundled. Cleared on success.

## Post-Login Redirect

`App\Services\UserHomeResolver::resolve()` = **single source of truth** for where any authenticated user lands. `admin`/`gestor` to `admin.dashboard`, everyone else (`aluno`) to `student.courses.index` — **if the named route exists** (`Route::has()` guard, `/` fallback). Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` delegate here, so logic never drifts between callers.

Never hardcode a URL. New role destination goes behind a `Route::has()` check so the resolver survives before its route exists. New role = update `UserHomeResolver::resolve()` (see `auth-orgs-maintenance`).

`redirect()->intended()` so a guest bounced to `/login` by `auth` middleware (or the `UnauthorizedException` guest-redirect in `bootstrap/app.php`) returns to the page originally asked for. `RedirectIfAuthenticated` also uses `intended()`, so the intended URL is honored on explicit login and when the `guest` guard intercepts an already-authenticated user at `/login`.

## Password Reset (RF02) — Single-Use Token via SMTP

Uses Laravel's password broker (`Illuminate\Auth\Passwords`), not a custom token table — `password_reset_tokens` came with the base `users` migration (SPEC-00 left it alone). `Password::sendResetLink()`/`Password::reset()` handle hashing, expiry (`config('auth.passwords.users.expire')`, 60 min) and **single-use deletion** of the token row. Second `POST /reset-password` with the same token fails with `passwords.token`, mapped to the `email` field.

**Delivery**: `App\Notifications\ResetPasswordNotification` extends `Illuminate\Auth\Notifications\ResetPassword` to localize copy (pt-BR) and reuses parent `resetUrl()` (resolves via `password.reset` named route — no manual URL building). `User::sendPasswordResetNotification()` overridden to dispatch it. Mailer = whatever `MAIL_MAILER`/`config('mail.php')` resolves (SMTP in production, `log` in local `.env`, `array` in tests via `phpunit.xml`). No auth-specific mail config.

## Testing Notes

- `status => 'active'` in `Auth::attempt()` means an inactive-user test needs only `User::factory()->inactive()->create(...)` plus a normal login POST. No separate path to stub.
- Reset tests use `Notification::fake()` + `Notification::assertSentTo($user, ResetPasswordNotification::class, fn ($n) => ...)` to pull the real `$notification->token` and drive the form (`tests/Feature/Auth/PasswordResetTest.php`). Never assert a hardcoded token.
- Dusk (`tests/Browser/Auth/LoginTest.php`) declares no DB trait — `DatabaseTruncation` comes from `Tests\DuskTestCase` (never `RefreshDatabase`; Dusk drives a separate HTTP process). Targets `dusk="login-*"` attributes, not CSS classes.

## Related

- `tenancy-architecture` — `RolesEnum`, `OrgScope`, Impersonate Org. Assumed here.
- `auth-orgs-conventions` — controller/policy/form-request conventions for Org CRUD, User CRUD, CSV Import (SPEC-04 Buckets B/C).
- `auth-orgs-maintenance` — RN09 edge cases, coverage checklist.
- `spec/specs/04-auth-profile-organizations-and-user-management.md` — RF01/RF02/RF23/RF04/RF05.
