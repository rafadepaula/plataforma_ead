---
name: auth-orgs-conventions
description: >
  Concrete code patterns, snippets, and guardrails shared by the Organization
  CRUD, User (Aluno/Gestor) CRUD, and CSV Import features in the Plataforma
  EAD auth/orgs module. Use whenever writing a controller, Policy, or Form
  Request that manages `Organization` or `User` records, whenever handling a
  file upload to the `public` disk, or whenever wiring an admin-only /
  gestor-only route.
license: MIT
metadata:
  feature: auth-orgs
  role: conventions
  specs:
    - spec/specs/04-auth-profile-organizations-and-user-management.md
---

# Auth/Orgs Conventions

## Controller Authorization: `Gate::authorize()` vs Form Request `authorize()`

The base `App\Http\Controllers\Controller` does **not** include Laravel's
`AuthorizesRequests` trait, so controller methods without a Form Request use
the `Gate` facade directly instead of `$this->authorize()`:

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

For `store`/`update` actions backed by a Form Request, push the same check
into the request's `authorize()` method instead of duplicating it in the
controller — the request already runs before the controller method body, so
a second `Gate::authorize()` call there would be dead code:

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

An unauthorized request from either path renders Laravel's default 403
response — `assertForbidden()` in feature tests, never a redirect. Route
`role:` middleware (see `tenancy-conventions`) is a first line of defense;
the Policy is the second, enforced independent of which route/middleware
group happens to reach the controller.

## Policies: One Role Check Per Ability, No Team Scoping

Policies in this module (`OrganizationPolicy`, and later `UserPolicy`) are
plain role checks against `RolesEnum`, resolved automatically by Laravel's
policy auto-discovery convention (`App\Models\{Model}` →
`App\Policies\{Model}Policy` — no manual registration needed in a service
provider):

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

`UserPolicy` (Bucket C) additionally has to compare `org_id`, not just role —
a Gestor may only manage Users within their own `org_id`, resolved the same
way `OrgScope::booted()` resolves it (`$user->org_id ?? session('active_org_id')`),
never trusting a route/request-supplied org id.

## Slug Auto-Generation with Collision Suffixing

`organizations.slug` is independently editable but auto-derived from `name`
when the caller submits a blank slug. Resolve it in the controller (not the
Form Request — the Request only validates shape/uniqueness of *whatever*
slug ends up submitted), appending a numeric suffix on collision rather than
failing validation:

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

An explicitly-submitted slug still goes through the normal
`unique:organizations,slug` (or `Rule::unique(...)->ignore($id)` on update)
rule and fails validation on collision — auto-suffixing is only a
convenience for the "let the system pick one" path.

## CNPJ Validation

`cnpj` is nullable + unique, validated against the two conventional Brazilian
formats (formatted or raw digits), and used only for certificate branding —
never for billing logic:

```php
'cnpj' => [
    'nullable',
    'string',
    'regex:/^(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})$/',
    'unique:organizations,cnpj', // or Rule::unique(...)->ignore($id) on update
],
```

## File Uploads to the `public` Disk

Logos (and any other admin-uploaded media in this module) go to the `public`
disk, under a feature-specific subdirectory, and the old file is deleted on
replacement:

```php
if ($request->hasFile('logo')) {
    if ($organization->logo_path) {
        Storage::disk('public')->delete($organization->logo_path);
    }
    $data['logo_path'] = $request->file('logo')->store('organizations/logos', 'public');
}
unset($data['logo']); // never mass-assign the UploadedFile itself
```

Tests fake the disk rather than touching real storage:

```php
Storage::fake('public');
// ...
Storage::disk('public')->assertExists($organization->logo_path);
```

## Impersonate Org: Validate Before Writing to Session

`ImpersonateOrgController::store()` must reject an inactive or nonexistent
Organization *before* writing to `session('active_org_id')` — never let a
stale/invalid id sit in the session for `OrgScope` to silently filter
everything down to zero rows:

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

Route-model-binding the `Organization` (rather than accepting a raw
`org_id` integer + manual lookup) gives a `404` for free on a nonexistent id,
before the controller body even runs. `destroy()` only ever calls
`session()->forget('active_org_id')` — it does not need to validate
anything.

## Factories: `inactive()` / `withCnpj()` States

Both `OrganizationFactory` and `UserFactory` follow the same state-method
convention already established for `status`/`cpf` — add a new named state
rather than passing raw overrides at every call site:

```php
Organization::factory()->inactive()->create();
Organization::factory()->withCnpj()->create();
```
