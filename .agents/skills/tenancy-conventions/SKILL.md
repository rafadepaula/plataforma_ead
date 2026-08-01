---
name: tenancy-conventions
description: >
  Concrete code patterns, snippets, and guardrails for writing org-scoped
  Eloquent models, migrations, controllers, and exception handling in the
  Plataforma EAD multitenancy module. Use whenever creating or modifying a
  migration with an `org_id` column, a model that should be tenant-isolated,
  code that reads/writes `session('active_org_id')`, or handling
  `UnresolvedOrgContextException`.
license: MIT
metadata:
  feature: tenancy
  role: conventions
  specs:
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Tenancy Conventions

## Applying `OrgScope` to a Model

Only apply the trait to models that own an `org_id` column directly (see the
"Directly org-scoped" list in `tenancy-architecture`). Do not apply it to
`User` or to cascade-inherited models (`Module`, `Lesson`, `Quiz`, ...) — those
inherit tenant boundaries through their parent relation.

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

Every org-scoped table's `org_id` column follows the same shape: explicit
`onDelete`, always indexed.

```php
$table->unsignedBigInteger('org_id')->nullable(); // nullable only where spec says so (users, help_articles, system_settings)
$table->foreign('org_id')->references('id')->on('organizations')->restrictOnDelete(); // or ->cascadeOnDelete() per spec §2.1
$table->index('org_id');
```

Use `restrictOnDelete()` (not `cascadeOnDelete()`) wherever SPEC-00 §2.1 says
`ON DELETE RESTRICT` — most notably `users.org_id`, so an Organization with
existing users can never be hard-deleted out from under them. Only soft-delete
(`deleted_at`) is available for Organizations in that state.

Do not edit the pre-existing base `0001_01_01_000000_create_users_table.php`
migration to add `org_id`/`cpf`/`status` — add a new, separate migration that
alters the table. Keeping the original Laravel migration untouched preserves
fresh-install history.

## Resolving the Active Org in Controllers/Requests

Never read `session('active_org_id')` ad hoc in multiple places with slightly
different fallback logic — always go through the same resolution order the
trait uses: `$user->org_id ?? session('active_org_id')`. If a controller needs
the resolved org id outside of model creation (e.g. to build a query manually,
or to render an Impersonate Org banner), extract that into a small helper
rather than duplicating the `??` chain, so both auto-assignment and read paths
never drift apart.

## Handling `UnresolvedOrgContextException`

The exception (`App\Exceptions\UnresolvedOrgContextException extends
RuntimeException`) is raised by `OrgScope::booted()` when creating an
org-scoped model with no resolvable `org_id`. It must never surface as a raw
500. Register it once, globally, in `bootstrap/app.php`'s exception handling —
do not catch it locally in individual controllers:

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
and AJAX/`X-Requested-With`) so both web (session-flash + redirect back) and
API/AJAX (JSON body) callers get a 422 in the shape they expect. Never let this
exception fall through to the default error page.

## Roles: Gate/Middleware Convention

Authorize by role via Spatie's `role:` middleware, matched against
`RolesEnum` values — do not hardcode role strings a second time:

```php
Route::middleware('role:' . RolesEnum::GESTOR->value)->group(function () {
    // gestor-only routes
});
```

Do not enable `config('permission.teams')`. Org partitioning is `org_id` +
`OrgScope`, never Spatie's team-scoped permissions — mixing the two would give
every org-scoped table two independent, easy-to-desync tenancy mechanisms.

## Factories & Tests

When building factories for org-scoped models, always set `org_id` explicitly
via a related `Organization::factory()` rather than leaving it to
`OrgScope::booted()`'s auto-assignment — tests should be explicit about which
org they're building data for, and factories often run outside an
authenticated context where auto-assignment would throw
`UnresolvedOrgContextException`.

```php
Course::factory()->for(Organization::factory())->create();
```

For polymorphic-ish "FK without a real foreign key" columns (`course_completion_rules.target_id`,
`forum_post_edits`/`forum_reports`'s `postable_type`/`postable_id`), there is
no DB constraint to rely on — validate the referenced record's existence and
its org membership explicitly in the Form Request or service layer.
