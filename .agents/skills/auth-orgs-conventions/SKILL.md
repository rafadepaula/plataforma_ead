---
name: auth-orgs-conventions
description: >
  Code patterns and guardrails shared by Organization CRUD, User
  (Aluno/Gestor) CRUD, and CSV Import in the auth/orgs module. Use when
  writing a controller, Policy, or Form Request managing `Organization` or
  `User`, handling upload to the `public` disk, or wiring an admin-only /
  gestor-only route.
license: MIT
metadata:
  feature: auth-orgs
  role: conventions
  specs:
    - spec/specs/04-auth-profile-organizations-and-user-management.md
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

`UserPolicy` (Bucket C) also compares `org_id`: Gestor manages only Users in own `org_id`, resolved like `OrgScope::booted()` does (`$user->org_id ?? session('active_org_id')`). Never trust a route/request-supplied org id.

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

## Guest Middleware Override

Framework `guest` alias (`Illuminate\Auth\Middleware\RedirectIfAuthenticated`) overridden in `bootstrap/app.php` to `App\Http\Middleware\RedirectIfAuthenticated`, which uses `App\Services\UserHomeResolver` for role-aware targets.

Authenticated user hitting `/login`, `/forgot-password`, `/reset-password/{token}` gets resolved to their role home via `UserHomeResolver::resolve()`, honoring `url.intended` in session.

**Convention:** all role-based redirect logic lives in `UserHomeResolver::resolve()`. Never duplicate it in controller or middleware. Both `AuthenticatedSessionController::store()` and `RedirectIfAuthenticated` call `app(UserHomeResolver::class)->resolve($user)`. New role destination = edit only `UserHomeResolver::resolve()`.

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
