---
name: profile-conventions
description: >
  Code patterns, snippets, guardrails for User Profile Self-Service:
  `profile.edit`/`profile.update`/`password.update`
  route-name contract, `dusk="profile-form"`/`dusk="password-form"`
  two-independent-forms Blade pattern, `Auth::logoutOtherDevices()`
  call-order requirement, where to add `App\Rules\Cpf` when new
  CPF-accepting entry point appears. Use when writing controller, Form
  Request, Blade view, or test touching `ProfileController`,
  `PasswordController`, or `App\Rules\Cpf`.
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

## `Auth::logoutOtherDevices()` Must Run *Before* Password Rotates

```php
public function update(PasswordUpdateRequest $request): RedirectResponse
{
    // Must run BEFORE the update below: it re-checks the plain
    // current_password against the guard's cached user, which is the
    // same object as $request->user() — updating the password first
    // makes this comparison fail against the already-rotated hash.
    Auth::logoutOtherDevices($request->string('current_password')->toString());

    $request->user()->update([
        'password' => Hash::make($request->string('password')->toString()),
    ]);

    return redirect()->route('profile.edit')->with('success', 'Senha alterada com sucesso.');
}
```

Never reorder these two calls. Never refactor to skip
`logoutOtherDevices()` own internal password check by trusting
`PasswordUpdateRequest` `current_password` rule alone. Both exist for
defense in depth, but only `logoutOtherDevices()` performs session
revocation as side effect of that check.

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
