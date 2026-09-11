---
name: profile-conventions
description: >
  Code patterns, snippets, guardrails for User Profile Self-Service:
  `profile.edit`/`profile.update`/`password.update`
  route-name contract, `dusk="profile-form"`/`dusk="password-form"`
  two-independent-forms Blade pattern, host-org credential
  password update with `remember_token` rotation, where to add
  `App\Rules\Cpf` when new CPF-accepting entry point appears. Use when
  writing controller, Form Request, Blade view, or test touching
  `ProfileController`, `PasswordController`, or `App\Rules\Cpf`.
license: MIT
metadata:
  feature: profile
  role: conventions
---

# Profile Conventions

## Route Contract

```php
route('profile.edit');      // GET   /profile         — ProfileController@edit
route('profile.update');    // PATCH /profile          — ProfileController@update
route('password.update');   // PUT   /profile/password — PasswordController@update, throttle:6,1
```

`password.update`, not `password.store`. `password.store` already belongs to
public reset flow (`Auth\NewPasswordController` in `routes/auth.php`).
Reusing that name collides, breaks password recovery. Both routes live under
single `Route::middleware('auth')` group in `routes/web.php` with **no**
`role:` restriction. Screen identical for every role.

## Password Change Writes The Host Org's Credential Only, And Rotates `remember_token`

```php
public function update(PasswordUpdateRequest $request): RedirectResponse
{
    $credential = $request->user()->credentialFor(OrgContext::current()->organization);

    if (! $credential) {
        abort(403, 'Conta não encontrada neste portal.');
    }

    $credential->forceFill([
        'password' => Hash::make($request->string('password')->toString()),
        'remember_token' => Str::random(60),
    ])->save();

    return redirect()->route('profile.edit')->with('success', 'Senha alterada com sucesso.');
}
```

Rules baked into this shape:

- **The target is the request host's `Credential`**, resolved via
  `User::credentialFor(OrgContext::current()->organization)` — never
  `$request->user()->update(['password' => ...])`: `users.password` no
  longer exists, and writing a "global" password would silently change
  (or invent) the person's account in the wrong org. Missing credential
  in this portal = 403, not a silent create.
- **`remember_token` rotates with the password.** Remember-me cookies
  issued by this portal die with the old token — the org-credential
  equivalent of `Auth::logoutOtherDevices()` (which is no longer called
  anywhere: it re-checks a plain current password against a global hash
  that does not exist).
- **Other active sessions are cut by middleware, not by this
  controller**: `AuthenticateSession` (`auth.session`, `web` group)
  fingerprints the credential hash, so stale sessions get logged out on
  their next request. `PasswordUpdateTest::test_changing_password_logs_out_other_active_sessions`
  drives this through the real middleware — do not "simplify" it to
  `actingAs()`.
- `PasswordUpdateRequest` validates `current_password` with Laravel's
  native rule, which re-checks the plain value against the authenticated
  guard's stored hash — `User::getAuthPassword()`, i.e. the host org
  credential's hash. Never duplicate that comparison in the controller.

Never reintroduce `Auth::logoutOtherDevices()` here: with per-org
credentials there is no single password for it to validate against, and
the same invalidation already happens through token rotation plus the
session middleware.

## Two Independent Forms, Two Independent Requests

`profile/edit.blade.php` renders two `<x-ui.card>` blocks, each with own
`<form>`, own CSRF/method spoofing, own `dusk` root attribute:

```blade
<form method="POST" action="{{ route('profile.update') }}" dusk="profile-form">
    @csrf @method('PATCH')
    ...
    <x-ui.button type="submit" dusk="profile-submit">Salvar Alterações</x-ui.button>
</form>
```

```blade
<form method="POST" action="{{ route('password.update') }}" dusk="password-form">
    @csrf @method('PUT')
    ...
    <x-ui.button type="submit" dusk="password-submit">Atualizar Senha</x-ui.button>
</form>
```

Submitting one never touches other fields. `ProfileUpdateRequest` has no
password fields; `PasswordUpdateRequest` has no name/email/cpf fields. Keep
split when adding any new profile field: goes in Block 1 with
`ProfileUpdateRequest`, never merged into password form.

## No Extra `<x-help-button>` on This Page

`layouts.app` topbar already mounts
`<x-help-button :key="Route::currentRouteName()" />` globally for every
authenticated screen, so `profile/edit.blade.php` intentionally adds no
second explicit `<x-help-button key="profile.edit" />`. Doing so renders two
buttons keyed to same route. See `help-conventions` for
global-vs-explicit button rule and which layouts (like `layouts.guest`)
require explicit form instead.

## Adding `App\Rules\Cpf` to New Entry Point

Every CPF-accepting Form Request follows same array-of-rules shape:

```php
'cpf' => ['nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')->ignore($userId)],
```

- `new Cpf` always comes before `unique` rule (cheap format check first).
- `ImportUsersChunkRequest` is sole exception. Do not add `Cpf` there; see
  `profile-architecture` and `auth-orgs-maintenance` for why.
- Failure message fixed by Rule itself (*"O CPF informado é inválido."*).
  Never override per-Request, or the uniform-message guarantee breaks.

## Topbar Link

`components/layout/topbar.blade.php` user dropdown has "Meu Perfil" item
pointing at `route('profile.edit')`, visible to every authenticated role (no
`@role`/`@can` gate). Mirror this when adding other universally-available
links.
