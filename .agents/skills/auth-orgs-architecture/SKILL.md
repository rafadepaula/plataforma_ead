---
name: auth-orgs-architecture
description: >
  Auth architecture (host-scoped login, per-org `credentials` accounts,
  `org-credential` user provider, remember-me per credential, role-based
  redirect, host-aware single-use password-reset token) and how it plugs
  into existing multitenancy (`ResolveOrgFromHost`, `OrgContext`,
  `OrgScope`, Impersonate Org, `RolesEnum`). Use when you need how a user
  authenticates on a portal host, why `status=active` credential gates
  login, how remember-me is validated per organization, how post-login
  redirect resolves per role, or how reset e-mail is delivered, before
  touching auth routes/controllers/views or code reading
  `Auth::user()`/`session('active_org_id')` at login/logout.
license: MIT
metadata:
  feature: auth-orgs
  role: architecture
---

# Auth + Orgs Architecture

## Overview

Auth = **custom** session auth (no Breeze/Fortify/Jetstream) on top of the
`users` (person: `name`, `email` unique, `cpf` unique) + `credentials`
(per-org account: `password` hashed, `status` active/inactive,
`remember_token`, `unique(user_id, org_id)`) pair and the
`RolesEnum`/`OrgScope` tenancy (see `tenancy-architecture`). No new
dependency. Login identity is the `(user, host org)` pair: e-mail lookup
is global (people are unique), password/status/token validation is
per-Organization.

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

Before any of this runs, `ResolveOrgFromHost` (first `web` middleware) bound the host's `OrgContext`; `EnsureTenantAccess` (appended to `web`) gates state zero / inactive org / credential-less sessions — see `tenancy-architecture`.

## The `org-credential` User Provider

`config/auth.php`'s `users` provider uses driver `org-credential`:
`App\Services\OrgCredentialUserProvider` (extends `EloquentUserProvider`,
registered in `App\Providers\AppServiceProvider` via
`Auth::provider('org-credential', ...)`). Passwords, statuses and
remember tokens live in `credentials`, one account per (user, host org):

- `retrieveByCredentials()` looks the person up **by e-mail only** —
  account-level keys are never translated into `users` WHERE clauses.
- `validateCredentials()` rejects an inactive Organization outright, then
  loads the `(user, OrgContext::orgId())` credential: no credential or
  `status !== 'active'` fails exactly like a wrong password — generic
  `auth.failed` is the ONLY client-visible outcome (anti-enumeration for
  both "not your portal" and "deactivated"). Hash check runs against the
  credential, with rehash-on-login.
- The global Admin credential (`org_id = null`) validates on every host —
  `sessionCredential()` falls back to it when the person holds no account
  on the host Organization (never overriding an existing host account),
  including state zero, where it is the only account that can pass.
- **Remember-me per credential**: `retrieveByToken()` requires the
  host-org credential to be `status = active` AND to hold the token (an
  inactive account never reacquires a session by cookie);
  `updateRememberToken()` writes the new token on that credential — not
  on `users` (column no longer exists).

## Rate Limiting

`LoginRequest` throttles on `lower(email)|orgId|ip` — the org part is
`OrgContext::current()->orgId() ?? 'global'` (`LoginRequest::throttleKey()`)
so attempts against one portal never lock someone out of another. 5
attempts via `RateLimiter`/`Illuminate\Auth\Events\Lockout`; cleared on
success.

## Post-Login Redirect

`App\Services\UserHomeResolver::resolve()` = **single source of truth** for where any authenticated user lands. `admin`/`gestor` to `admin.dashboard`, `professor` to `professor.dashboard`, everyone else (`aluno`) to `student.courses.index` — **if the named route exists** (`Route::has()` guard, `/` fallback). Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` delegate here, so logic never drifts between callers.

Never hardcode a URL. New role destination goes behind a `Route::has()` check so the resolver survives before its route exists. New role = update `UserHomeResolver::resolve()` (see `auth-orgs-maintenance`).

`redirect()->intended()` so a guest bounced to `/login` by `auth` middleware (or the `UnauthorizedException` guest-redirect in `bootstrap/app.php`) returns to the page originally asked for. `RedirectIfAuthenticated` also uses `intended()`, so the intended URL is honored on explicit login and when the `guest` guard intercepts an already-authenticated user at `/login`.

## Password Reset — Host-Aware, Single-Use Token via SMTP

Uses Laravel's password broker (`Illuminate\Auth\Passwords`) —
`password_reset_tokens` came with the base `users` migration.
`Password::sendResetLink()`/`Password::reset()` handle hashing, expiry
(`config('auth.passwords.users.expire')`, 60 min) and **single-use
deletion** of the token row. Second `POST /reset-password` with the same
token fails with `passwords.token`, mapped to the `email` field.

**Host scoping**: `PasswordResetLinkController::hasAccountInContextOrg()`
only sends a link when the person holds a credential in the request
host's Organization (`Credential::forOrg()`; `org_id = null` matches the
global Admin — usable in state zero, where reset is Admin-only). Resetting
from portal B never touches portal A's password. `NewPasswordController`
applies the new password to the HOST org's credential only and rotates
its `remember_token` in the same write. **Inactive org blocks both ends**:
forgot reads as unknown e-mail, and a token issued before the
deactivation is rejected with the generic `Password::INVALID_TOKEN` — no
oracle about the Organization's state.

**Delivery**: `App\Notifications\ResetPasswordNotification` extends `Illuminate\Auth\Notifications\ResetPassword` to localize copy (pt-BR) and reuses parent `resetUrl()` (resolves via `password.reset` named route — no manual URL building). `User::sendPasswordResetNotification()` overridden to dispatch it. Mailer = whatever `MAIL_MAILER`/`config('mail.php')` resolves (SMTP in production, `log` in local `.env`, `array` in tests via `phpunit.xml`). No auth-specific mail config.

## Password Surfaces Read the Host Credential

`User::getAuthPassword()` returns the host-org credential's hash: it
feeds the `current_password` confirmation rule and
`AuthenticateSession`'s session fingerprint (appended to `web` via
`$middleware->authenticateSessions()` in `bootstrap/app.php` — a password
change force-logs-out other sessions). `User::credentialFor(?Organization)`
is the canonical single-account lookup. Password update/change screens
write the credential, never `users`. Deactivation or password set on the
global Admin screen flips/rotates **every** credential of the person
(`UserAdminController`).

## Testing Notes

- Host pinning is `TestCase::onHost()`/`actingAsAdmin()`/`actingAsOrgUser()` — see `testing-architecture`.
- `tests/Feature/Auth/HostScopedLoginTest.php` (login per host, inactive
  credential, inactive org, state-zero admin-only, remember-me per
  credential, throttle per org), `tests/Feature/Auth/PasswordResetHostTest.php`
  (host-scoped reset incl. inactive-org blocking) and
  `tests/Feature/PasswordUpdateTest.php` (current-password against the
  credential, other-session logout) are the contract suites.
- Reset tests use `Notification::fake()` + `Notification::assertSentTo($user, ResetPasswordNotification::class, fn ($n) => ...)` to pull the real `$notification->token` and drive the form (`tests/Feature/Auth/PasswordResetTest.php`). Never assert a hardcoded token.
- Dusk (`tests/Browser/Auth/LoginTest.php`) declares no DB trait — `DatabaseTruncation` comes from `Tests\DuskTestCase` (never `RefreshDatabase`; Dusk drives a separate HTTP process). Targets `dusk="login-*"` attributes, not CSS classes.

## Global Admin User-Management Screen (`admin.users.*`)

The operational `users.index` (above/`UserController`) is single-Organization by design: `ResolvesOrgContext` throws `UnresolvedOrgContextException` for an Admin with no `session('active_org_id')`, and the query filters people holding a credential in the resolved org. A **second, deliberately separate** screen — `admin/users` (`admin.users.index|show|edit|update|status|destroy`) — serves cross-org administration of all four roles (admin/gestor/aluno/professor), registered inside the `role:admin`-only route group (not `role:admin|gestor`), so Gestor/Aluno are blocked by middleware first, Policy second.

`App\Http\Controllers\Admin\UserAdminController` does **not** extend `UserController` and does **not** use `ResolvesOrgContext` — the listing is global by definition (no `org_id` is ever resolved from the acting Admin's session; `org_id` only appears as an optional *filter* over the person's credentials). It reuses `User`/`RolesEnum`/the `user.status_changed` audit event, but is otherwise a fully independent controller/request/view stack.

Authorization is a second, parallel set of `UserPolicy` abilities — `viewAnyGlobal`/`viewGlobal`/`updateGlobal`/`deleteGlobal` — plain `hasRole(ADMIN)` checks with **no** `sharesOrgContext()` involved (an Admin here acts globally, there is no single org to compare). The existing `sharesOrgContext()`-driven `viewAny`/`view`/`update`/`delete` abilities that gate the operational screen are untouched, so relaxing global admin access can never accidentally loosen multi-tenant isolation on `users.index`. `sharesOrgContext()` compares **credential membership** (`UserPolicy::holdsAccountIn()`): Admin needs an impersonated `session('active_org_id')` the target person holds an account in; Gestor needs the target to hold an account in the host org. `deleteGlobal` additionally blocks self-deletion (`$user->id !== $model->id`); `UserAdminController::update()`/`updateStatus()` separately guard against self-deactivation and self-demotion-from-admin in the controller body (403, not validation).

`destroy()` performs two pre-flight existence checks before the hard delete, since `users` has no `deleted_at` and both FKs are `ON DELETE RESTRICT`: `certificates.user_id` (`UserHasIssuedCertificatesException`) and `invitation_links.created_by` (`UserHasCreatedInvitationLinksException`, checked with `withoutGlobalScope('org')` since `InvitationLink` **is** `OrgScope`d and an Admin with no active impersonation would otherwise miss links from other Organizations). Both exist to turn a raw 500 `QueryException` into a friendly, catchable error.

`UpdateUserAdminRequest` (distinct from `UpdateUserRequest`) is the "full profile" editor: `role` accepts all 4 `RolesEnum` values. Org membership and account status are **not** editable here — under host-based tenancy each Organization account carries its own status and memberships are managed on each portal. A nullable `password` resets ALL of the person's credentials (with `rotateRememberToken()`); `prepareForValidation()` only normalizes CPF digits.

## Related

- `tenancy-architecture` — host resolution chain, `OrgContext`, `credentials`, `RolesEnum`, `OrgScope`, Impersonate Org. Assumed here.
- `auth-orgs-conventions` — controller/policy/form-request conventions for Org CRUD, User CRUD, CSV Import, and the global-admin patterns.
- `auth-orgs-maintenance` — edge cases, coverage checklist, test contract.
