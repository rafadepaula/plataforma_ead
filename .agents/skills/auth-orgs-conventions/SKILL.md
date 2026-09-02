---
name: auth-orgs-conventions
description: >
  Code patterns and guardrails shared by Organization CRUD, User
  (Aluno/Gestor) CRUD, and CSV Import in the auth/orgs module. Use when
  writing a controller, Policy, or Form Request managing `Organization` or
  `User`, handling upload to the `public` disk, wiring an admin-only /
  gestor-only route, or editing the guest-shell auth views
  (`auth/login.blade.php`, `layouts/guest.blade.php`, `layout/guest-panel`),
  whose heading level, password toggle, `dusk=` hooks and no-self-signup rule
  are contract.
license: MIT
metadata:
  feature: auth-orgs
  role: conventions
---

# Auth/Orgs Conventions

## Authorization: `Gate::authorize()` vs Form Request `authorize()`

Base `App\Http\Controllers\Controller` has no `AuthorizesRequests` trait. Controller methods without a Form Request use the `Gate` facade, not `$this->authorize()`:

```php
use Illuminate\Support\Facades\Gate;

public function index(): View
{
    Gate::authorize('viewAny', Organization::class);
    // ...
}

public function destroy(Organization $organization): RedirectResponse
{
    Gate::authorize('delete', $organization);
    $organization->delete();
    // ...
}
```

`store`/`update` backed by Form Request: put the check in the request's `authorize()`. Request runs before controller body, so a second `Gate::authorize()` there is dead code:

```php
class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Organization::class) ?? false;
    }
}

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }
}
```

Unauthorized from either path = Laravel default 403. Feature tests assert `assertForbidden()`, never a redirect. Route `role:` middleware (`tenancy-conventions`) is first defense; Policy is second, enforced whatever route reaches the controller.

## Policies: One Role Check Per Ability, No Team Scoping

Policies here (`OrganizationPolicy`, later `UserPolicy`) = plain role checks against `RolesEnum`, auto-discovered (`App\Models\{Model}` to `App\Policies\{Model}Policy`, no provider registration):

```php
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }
}
```

`UserPolicy` also compares `org_id`: Gestor manages only Users in own `org_id`, resolved like `OrgScope::booted()` does (`$user->org_id ?? session('active_org_id')`). Never trust a route/request-supplied org id.

## Slug Auto-Generation with Collision Suffix

`organizations.slug` editable, auto-derived from `name` when submitted blank. Resolve in controller, not Form Request (Request only validates shape/uniqueness of whatever slug arrives). Append numeric suffix on collision instead of failing:

```php
private function resolveSlug(string $name, ?string $slug): string
{
    if ($slug) {
        return $slug;
    }

    $base = Str::slug($name);
    $candidate = $base;
    $suffix = 2;

    while (Organization::query()->where('slug', $candidate)->exists()) {
        $candidate = "{$base}-{$suffix}";
        $suffix++;
    }

    return $candidate;
}
```

Explicit slug still goes through `unique:organizations,slug` (or `Rule::unique(...)->ignore($id)` on update) and fails on collision. Auto-suffix serves only the "system picks" path.

## CNPJ Validation

`cnpj` nullable + unique, two Brazilian formats (formatted or 14 raw digits), used only for certificate branding — never billing:

```php
'cnpj' => [
    'nullable',
    'string',
    'regex:/^(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})$/',
    'unique:organizations,cnpj', // or Rule::unique(...)->ignore($id) on update
],
```

## Uploads to `public` Disk

Logos and other admin media go to `public` disk under a feature subdirectory. Old file deleted on replace:

```php
if ($request->hasFile('logo')) {
    if ($organization->logo_path) {
        Storage::disk('public')->delete($organization->logo_path);
    }
    $data['logo_path'] = $request->file('logo')->store('organizations/logos', 'public');
}
unset($data['logo']); // never mass-assign the UploadedFile itself
```

Tests fake the disk:

```php
Storage::fake('public');
// ...
Storage::disk('public')->assertExists($organization->logo_path);
```

## Impersonate Org: Validate Before Session Write

`ImpersonateOrgController::store()` rejects inactive/nonexistent Organization *before* writing `session('active_org_id')`. Stale id in session makes `OrgScope` filter everything to zero rows silently:

```php
public function store(Organization $organization): RedirectResponse
{
    if ($organization->status !== 'active') {
        throw ValidationException::withMessages([
            'organization' => 'Só é possível assumir o contexto de uma Organização ativa.',
        ]);
    }

    session(['active_org_id' => $organization->id]);

    return back()->with('success', "Contexto alterado para a Organização \"{$organization->name}\".");
}
```

Route-model-binding `Organization` (not raw `org_id` + manual lookup) gives 404 free on nonexistent id before the body runs. `destroy()` only calls `session()->forget('active_org_id')`, validates nothing.

## Factories: `inactive()` / `withCnpj()` States

`OrganizationFactory` and `UserFactory` follow the state-method convention already used for `status`/`cpf`. Add a named state, do not pass raw overrides at every call site:

```php
Organization::factory()->inactive()->create();
Organization::factory()->withCnpj()->create();
```

## `auth/login.blade.php`: Guest Shell Markup Contract

The login screen is markup over `layouts.guest` (split shell: institutional
panel `col-lg-5`/`--blue-100`, 440px form column). Four rules a change here must
keep:

1. Heading is `<x-layout.page-header kicker="Acesso" title="Entrar na plataforma"
   level="h2" />`. **`level="h2"` is required** — the guest panel already renders
   the page's only `<h1>`; the default `page-header` would emit a second one.
2. Never write a literal `*` into a label. `<x-ui.input required>` already
   appends the required marker; a hand-typed asterisk renders it twice.
3. The password reveal uses the existing `.password-toggle-btn` inside
   `.password-field` (styled in `_utilities.scss`) driven by `PasswordToggle`,
   which swaps `.d-none` on the `eye`/`eye-off` icons. No ad-hoc positioning
   utilities, no second toggle implementation.
4. All six `dusk=` hooks (`login-form`, `login-email`, `login-password`,
   `login-remember`, `login-submit`, `forgot-password-link`) and the
   `data-password-toggle-*` attributes stay on the **same** nodes — they are an
   E2E contract asserted by `tests/Browser/Auth/LoginTest.php`, and
   `DuskSelectorContractTest` fails on a moved or dropped selector.

There is deliberately **no self-signup path** on this screen: no link to
`/register`, no "Criar conta"/"Cadastre-se" copy. Students enter the platform
only through an invitation link (see `invitations-architecture`) or a
Gestor/Admin-created account. Credential rejection is a single generic message
for both a wrong password and a non-existent e-mail (anti-enumeration), rendered
in the `--critical` pastel alert, never red.

## Guest Middleware Override

Framework `guest` alias (`Illuminate\Auth\Middleware\RedirectIfAuthenticated`) overridden in `bootstrap/app.php` to `App\Http\Middleware\RedirectIfAuthenticated`, which uses `App\Services\UserHomeResolver` for role-aware targets.

Authenticated user hitting `/login`, `/forgot-password`, `/reset-password/{token}` gets resolved to their role home via `UserHomeResolver::resolve()`, honoring `url.intended` in session.

**Convention:** all role-based redirect logic lives in `UserHomeResolver::resolve()`. Never duplicate it in controller or middleware. Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` call `app(UserHomeResolver::class)->resolve($user)`. New role destination = edit only `UserHomeResolver::resolve()`.

## Parallel "Global" Policy Abilities Instead of Relaxing `sharesOrgContext()`

When a new screen needs an Admin to act across every Organization while an existing screen must keep enforcing tenant isolation for the *same* model, add a **second set of named abilities** rather than branching inside the existing ones. The global Admin user-management screen added `viewAnyGlobal`/`viewGlobal`/`updateGlobal`/`deleteGlobal` to `UserPolicy` next to the untouched `viewAny`/`view`/`update`/`delete`:

```php
public function viewGlobal(User $user, User $model): bool
{
    return $user->hasRole(RolesEnum::ADMIN->value);
}

// original, unchanged, still gates users.* :
public function view(User $user, User $model): bool
{
    return $this->sharesOrgContext($user, $model);
}
```

Route them to a **separate controller** (`Admin\UserAdminController`, not `UserController`) that never uses `ResolvesOrgContext`, and register the routes inside the `role:admin`-only middleware group — never `role:admin|gestor` — so the screen is unreachable to a Gestor at the middleware layer too, not just the Policy. Never add an `if ($isGlobalScreen)` branch to an existing ability; that is exactly how tenant isolation on the operational screen regresses.

## Self-Action Guards Belong in the Controller, Not the Policy

Blocking an actor from deactivating/deleting *themselves* is state that depends on which specific row is being mutated versus who is logged in — a business rule, not an authorization rule about the resource type. `UserPolicy::deleteGlobal()` does fold in `$user->id !== $model->id` (delete is unconditionally forbidden on your own row), but the softer "you may delete/deactivate other users, just not update your own status/role right now" checks live as explicit `abort(403, '...')` calls inside `UserAdminController::update()`/`updateStatus()`, after the Policy gate already passed:

```php
if (($data['status'] ?? null) === 'inactive' && $user->id === Auth::id()) {
    abort(403, 'Você não pode desativar sua própria conta.');
}
```

## Guard Hard Deletes Behind `ON DELETE RESTRICT` FKs

`users` has no `deleted_at` (no `SoftDeletes`). Before calling `$user->delete()` on any screen, check every FK pointing at `users.id` that is `ON DELETE RESTRICT` and throw a dedicated, catchable exception instead of letting a raw `QueryException` 500 out:

```php
if ($user->certificates()->exists()) {
    throw new UserHasIssuedCertificatesException(...);
}

// InvitationLink IS OrgScope'd — bypass the scope or an Admin with no
// active impersonation (or impersonating a different Org) silently
// misses links created in other Organizations and still crashes.
if ($user->createdInvitationLinks()->withoutGlobalScope('org')->exists()) {
    throw new UserHasCreatedInvitationLinksException(...);
}
```

New FK referencing `users.id` with `ON DELETE RESTRICT` = add its own pre-flight check here (and in the operational `UserController::destroy()` if that path is also reachable).

## Full-Profile vs Partial Form Requests for the Same Model

`UpdateUserAdminRequest` (global screen) is a distinct Form Request from `UpdateUserRequest` (operational screen), not a superset flag on one class — `role` allows all 3 `RolesEnum` values, `org_id` is editable and conditionally required/prohibited via `Rule::requiredIf()`/`Rule::prohibitedIf()` keyed off the submitted `role`, forced to `null` for `role === admin` in `prepareForValidation()` so a stale `org_id` in the payload can never leak through when the caller only changed the role select:

```php
protected function prepareForValidation(): void
{
    if ($this->input('role') === RolesEnum::ADMIN->value) {
        $this->merge(['org_id' => null]);
    }
}
```

Keep two Form Requests per model when the "same-looking" edit screen has a genuinely different validation surface for a different actor/scope — do not parameterize one Request class with a role/mode flag.

## Confirm-Modal Row Actions That Need Extra Fields: Encode Them in the `action` URL

`<x-ui.confirm-modal>`'s embedded `<form>` has no slot for hidden fields beyond its own confirm button. When a same-route action (e.g. the admin listing's ativar/desativar toggle, `admin.users.status`) needs one extra non-PII value alongside the route-bound model, append it to the modal's `:action` route call — `Illuminate\Http\Request` merges query string into `$request->validate()`/`->input()` reads:

```blade
<x-ui.confirm-modal id="confirm-status-{{ $user->id }}"
                     :action="route('admin.users.status', ['user' => $user->id, 'status' => $toggledStatus])"
                     method="PATCH" ... />
```

Only do this for non-sensitive scalars (a status enum value here) — never put name/e-mail/CPF or anything PII in a query string that ends up in server logs.

## `admin/users/show.blade.php`: Read-Only Enrollment/Certificate Cards Need Explicit Eager Load

The global admin user-detail screen adds two read-only `<x-ui.card>`s beside the original `<dl>` (which keeps its `dusk=` attributes untouched): "Matrículas" from `$user->courses` (title, enrolled date, pivot status badge) and "Certificados" from `$user->certificates` (course title, issued date, revoked/emitido badge — `accent-2`/`accent` variants, never `danger`). Both relations must be added to `UserAdminController::show()`'s `$user->load([...])` call (`courses`, `certificates.course`) — the base `['organization', 'roles']` load predates this and does not cover them; add any further relation the show view starts rendering the same way rather than letting the view lazy-load per row.

## `UserHomeResolver`

```php
// app/Services/UserHomeResolver.php
class UserHomeResolver
{
    public function resolve(User $user): string
    {
        if ($user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return Route::has('admin.dashboard') ? route('admin.dashboard') : '/';
        }

        return Route::has('student.courses.index') ? route('student.courses.index') : '/';
    }
}
```

Callers always resolve from container: `app(UserHomeResolver::class)->resolve($user)`. No manual instantiation.
