---
name: tenancy-conventions
description: >
  Code patterns, snippets, guardrails for org-scoped Eloquent models,
  migrations, controllers, exception handling in Plataforma EAD multitenancy
  module. Use when create or modify migration with `org_id` column, model that
  must be tenant-isolated, code reading/writing `session('active_org_id')`, or
  handling `UnresolvedOrgContextException`.
license: MIT
metadata:
  feature: tenancy
  role: conventions
---

# Tenancy Conventions

## Applying `OrgScope` to a Model

Trait only on models owning `org_id` column directly (see "Directly org-scoped"
list in `tenancy-architecture`). Never on `User`. Never on cascade-inherited
models (`Module`, `Lesson`, `Quiz`, ...) — they inherit tenant boundary through
parent relation.

```php
namespace App\Models;

use App\Models\Traits\OrgScope;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use OrgScope;

    protected $fillable = ['org_id', 'title', 'description', 'workload_hours', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
```

## Migration Convention for `org_id`

Every org-scoped table `org_id` column same shape: explicit `onDelete`, always
indexed.

```php
$table->unsignedBigInteger('org_id')->nullable(); // nullable only for users, help_articles, system_settings
$table->foreign('org_id')->references('id')->on('organizations')->restrictOnDelete(); // or ->cascadeOnDelete() for tables whose rows the org owns outright
$table->index('org_id');
```

Use `restrictOnDelete()`, not `cascadeOnDelete()`, wherever a hard delete must
be blocked — most of all `users.org_id`, so an Organization with existing users
is never hard-deleted under them. Only soft-delete (`deleted_at`) available
for Organizations in that state.

Never edit pre-existing base `0001_01_01_000000_create_users_table.php`
migration to add `org_id`/`cpf`/`status`. Add new separate migration that alters
table. Untouched original Laravel migration preserve fresh-install history.

## Resolving the Active Org in Controllers/Requests

Never read `session('active_org_id')` ad hoc in many places with slightly
different fallback logic. Always same resolution order trait use:
`$user->org_id ?? session('active_org_id')`. Controller needing resolved org id
outside model creation (build query manually, render Impersonate Org banner):
extract small helper, no duplicated `??` chain, so auto-assignment path and read
path never drift apart.

## Handling `UnresolvedOrgContextException`

Exception (`App\Exceptions\UnresolvedOrgContextException extends
RuntimeException`) raised by `OrgScope::booted()` when creating org-scoped model
with no resolvable `org_id`. Must never surface as raw 500. Register once,
globally, in `bootstrap/app.php` exception handling. Never catch locally in
individual controllers:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (UnresolvedOrgContextException $e, Request $request) {
        $message = 'Selecione uma Organização ativa antes de continuar.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withInput()->with('error', $message);
    });
});
```

Content-negotiate on `$request->expectsJson()` (covers `Accept: application/json`
and AJAX/`X-Requested-With`) so web caller (session-flash + redirect back) and
API/AJAX caller (JSON body) both get 422 in shape they expect. Never let this
exception fall through to default error page.

## Roles: Gate/Middleware Convention

Authorize by role via Spatie `role:` middleware, matched against `RolesEnum`
values. Never hardcode role string second time:

```php
Route::middleware('role:' . RolesEnum::GESTOR->value)->group(function () {
    // gestor-only routes
});
```

Never enable `config('permission.teams')`. Org partitioning is `org_id` +
`OrgScope`, never Spatie team-scoped permissions. Mixing two give every
org-scoped table two independent, easy-to-desync tenancy mechanisms.

## Factories & Tests

Factories for org-scoped models: always set `org_id` explicit via related
`Organization::factory()`, never leave to `OrgScope::booted()` auto-assignment.
Tests must be explicit which org they build data for, and factories often run
outside authenticated context where auto-assignment throw
`UnresolvedOrgContextException`.

```php
Course::factory()->for(Organization::factory())->create();
```

Polymorphic-ish "FK without real foreign key" columns
(`course_completion_rules.target_id`,
`forum_post_edits`/`forum_reports` `postable_type`/`postable_id`): no DB
constraint to lean on. Validate referenced record existence and its org
membership explicit in Form Request or service layer.
