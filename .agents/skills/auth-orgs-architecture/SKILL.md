---
name: auth-orgs-architecture
description: >
  Auth architecture (login, role-based redirect, single-use
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
---

# Auth + Orgs Architecture

## Overview

Auth = **custom** session auth (no Breeze/Fortify/Jetstream) on top of
`users` table and the `RolesEnum`/`OrgScope` tenancy (see
`tenancy-architecture`). No new dependency, no new migration. Auth only
*reads* existing `email`, `password`, `status`, `org_id` columns and Spatie roles.

## Why Custom Controllers

`composer.json` has no `laravel/breeze`/`fortify`/`jetstream`, and CLAUDE.md forbids changing dependencies without approval. The scope (email+password login, role check, single-use reset token) is small, so hand-written controllers mirror Breeze's pattern (`LoginRequest::authenticate()`, `Password::sendResetLink()`/`Password::reset()`) without a scaffolding package or its view stack.

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

All six in `routes/auth.php`, required from `routes/web.php` — keeps the auth surface reviewable in one file. `login`/`forgot-password`/`reset-password/{token}` GET behind `guest` alias; `logout` behind `auth`. `auth` alias stays framework default. `guest` is **overridden** in `bootstrap/app.php` with `App\Http\Middleware\RedirectIfAuthenticated`, which gives role-aware targets via `UserHomeResolver` instead of the framework fallback to `/`.

## `status=active` Gate

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

## Password Reset — Single-Use Token via SMTP

Uses Laravel's password broker (`Illuminate\Auth\Passwords`), not a custom token table — `password_reset_tokens` came with the base `users` migration. `Password::sendResetLink()`/`Password::reset()` handle hashing, expiry (`config('auth.passwords.users.expire')`, 60 min) and **single-use deletion** of the token row. Second `POST /reset-password` with the same token fails with `passwords.token`, mapped to the `email` field.

**Delivery**: `App\Notifications\ResetPasswordNotification` extends `Illuminate\Auth\Notifications\ResetPassword` to localize copy (pt-BR) and reuses parent `resetUrl()` (resolves via `password.reset` named route — no manual URL building). `User::sendPasswordResetNotification()` overridden to dispatch it. Mailer = whatever `MAIL_MAILER`/`config('mail.php')` resolves (SMTP in production, `log` in local `.env`, `array` in tests via `phpunit.xml`). No auth-specific mail config.

## Testing Notes

- `status => 'active'` in `Auth::attempt()` means an inactive-user test needs only `User::factory()->inactive()->create(...)` plus a normal login POST. No separate path to stub.
- Reset tests use `Notification::fake()` + `Notification::assertSentTo($user, ResetPasswordNotification::class, fn ($n) => ...)` to pull the real `$notification->token` and drive the form (`tests/Feature/Auth/PasswordResetTest.php`). Never assert a hardcoded token.
- Dusk (`tests/Browser/Auth/LoginTest.php`) declares no DB trait — `DatabaseTruncation` comes from `Tests\DuskTestCase` (never `RefreshDatabase`; Dusk drives a separate HTTP process). Targets `dusk="login-*"` attributes, not CSS classes.

## Global Admin User-Management Screen (`admin.users.*`)

The operational `users.index` (above/`UserController`) is single-Organization by design: `ResolvesOrgContext` throws `UnresolvedOrgContextException` for an Admin with no `session('active_org_id')`, and the query is hard-scoped to `aluno`/`gestor`. A **second, deliberately separate** screen — `admin/users` (`admin.users.index|show|edit|update|status|destroy`) — serves cross-org administration of all three roles (admin/gestor/aluno), registered inside the `role:admin`-only route group (not `role:admin|gestor`), so Gestor/Aluno are blocked by middleware first, Policy second.

`App\Http\Controllers\Admin\UserAdminController` does **not** extend `UserController` and does **not** use `ResolvesOrgContext` — the listing is global by definition (no `org_id` is ever resolved from the acting Admin's session; `org_id` only appears as an optional *filter* from the request). It reuses `User`/`RolesEnum`/the `user.status_changed` audit event, but is otherwise a fully independent controller/request/view stack.

Authorization is a second, parallel set of `UserPolicy` abilities — `viewAnyGlobal`/`viewGlobal`/`updateGlobal`/`deleteGlobal` — plain `hasRole(ADMIN)` checks with **no** `sharesOrgContext()` involved (an Admin here acts globally, there is nothing to compare `org_id` against). The existing `sharesOrgContext()`-driven `viewAny`/`view`/`update`/`delete` abilities that gate the operational screen are untouched, so relaxing global admin access can never accidentally loosen multi-tenant isolation on `users.index`. `deleteGlobal` additionally blocks self-deletion (`$user->id !== $model->id`); `UserAdminController::update()`/`updateStatus()` separately guard against self-deactivation and self-demotion-from-admin in the controller body (403, not validation).

`destroy()` performs two pre-flight existence checks before the hard delete, since `users` has no `deleted_at` and both FKs are `ON DELETE RESTRICT`: `certificates.user_id` (`UserHasIssuedCertificatesException`) and `invitation_links.created_by` (`UserHasCreatedInvitationLinksException`, checked with `withoutGlobalScope('org')` since `InvitationLink` **is** `OrgScope`d and an Admin with no active impersonation would otherwise miss links from other Organizations). Both exist to turn a raw 500 `QueryException` into a friendly, catchable error.

`UpdateUserAdminRequest` (distinct from `UpdateUserRequest`) is the "full profile" editor: `role` accepts all 3 `RolesEnum` values (not just aluno/gestor), and `org_id` is editable — required unless `role === 'admin'`, forced to `null` in `prepareForValidation()` whenever `role === 'admin'` regardless of what stale value the form posted.

## Related

- `tenancy-architecture` — `RolesEnum`, `OrgScope`, Impersonate Org. Assumed here.
- `auth-orgs-conventions` — controller/policy/form-request conventions for Org CRUD, User CRUD, CSV Import, and the global-admin patterns.
- `auth-orgs-maintenance` — edge cases, coverage checklist, test contract.
