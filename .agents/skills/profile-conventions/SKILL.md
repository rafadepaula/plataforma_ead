---
name: profile-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the User Profile
  Self-Service feature (SPEC-18/UC02): the `profile.edit`/`profile.update`/
  `password.update` route-name contract, the `dusk="profile-form"`/
  `dusk="password-form"` two-independent-forms Blade pattern, the
  `Auth::logoutOtherDevices()` call-order requirement, and where to add
  `App\Rules\Cpf` when a new CPF-accepting entry point appears. Use
  whenever writing a controller, Form Request, Blade view, or test that
  touches `ProfileController`, `PasswordController`, or `App\Rules\Cpf`.
license: MIT
metadata:
  feature: profile
  role: conventions
  specs:
    - spec/specs/18-user-profile-management.md
    - spec/docs/usecases/UC02-gestao-de-perfil-do-usuario.md
---

# Profile Conventions

## Route Contract

```php
route('profile.edit');      // GET   /profile         — ProfileController@edit
route('profile.update');    // PATCH /profile          — ProfileController@update
route('password.update');   // PUT   /profile/password — PasswordController@update, throttle:6,1
```

`password.update`, not `password.store` — `password.store` already
belongs to the public reset flow (`Auth\NewPasswordController` in
`routes/auth.php`, UC01). Reusing that name would collide and break
password recovery. Both routes live under a single `Route::middleware('auth')`
group in `routes/web.php` with **no** `role:` restriction — the screen is
identical for every role.

## `Auth::logoutOtherDevices()` Must Run *Before* the Password Is Rotated

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

Never reorder these two calls, and never refactor to skip
`logoutOtherDevices()`'s own internal password check by trusting
`PasswordUpdateRequest`'s `current_password` rule alone — both exist for
defense in depth, but only `logoutOtherDevices()` actually performs the
session revocation as a side effect of that check.

## Two Independent Forms, Two Independent Requests

`profile/edit.blade.php` renders two `<x-ui.card>` blocks, each with its
own `<form>`, its own CSRF/method spoofing, and its own `dusk` root
attribute:

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

Submitting one never touches the other's fields — `ProfileUpdateRequest`
has no password fields and `PasswordUpdateRequest` has no name/email/cpf
fields. Keep this split when adding any new profile field: it goes in
Block 1 with `ProfileUpdateRequest`, never merged into the password form.

## No Extra `<x-help-button>` on This Page

`layouts.app`'s topbar already mounts
`<x-help-button :key="Route::currentRouteName()" />` globally for every
authenticated screen, so `profile/edit.blade.php` intentionally adds no
second explicit `<x-help-button key="profile.edit" />` — doing so would
render two buttons keyed to the same route. See `help-conventions` for
the global-vs-explicit button rule and which layouts (like
`layouts.guest`) require the explicit form instead.

## Adding `App\Rules\Cpf` to a New Entry Point

Every CPF-accepting Form Request follows the same array-of-rules shape:

```php
'cpf' => ['nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')->ignore($userId)],
```

- `new Cpf` always comes before the `unique` rule (cheap format check
  first).
- `ImportUsersChunkRequest` is the sole exception — do not add `Cpf`
  there; see `profile-architecture` and `auth-orgs-maintenance` for why.
- The failure message is fixed by the Rule itself
  (*"O CPF informado é inválido."*) — never override it per-Request, or
  the uniform-message guarantee RN17 asks for breaks.

## Topbar Link

`components/layout/topbar.blade.php`'s user dropdown has a "Meu Perfil"
item pointing at `route('profile.edit')`, visible to every authenticated
role (no `@role`/`@can` gate) — mirror this when adding other
universally-available links.
