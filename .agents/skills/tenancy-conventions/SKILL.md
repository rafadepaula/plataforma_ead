---
name: tenancy-conventions
description: >
  Code patterns, snippets, guardrails for org-scoped Eloquent models,
  migrations, controllers, host-based tenant context
  (`OrgContext`/`ResolveOrgFromHost`), credential-backed accounts and
  exception handling in Plataforma EAD multitenancy module. Use when
  create or modify migration with `org_id` column, model that must be
  tenant-isolated, code reading the request host Organization, writing
  outside a web request (console/queue/factories), or handling
  `UnresolvedOrgContextException`.
license: MIT
metadata:
  feature: tenancy
  role: conventions
---

# Tenancy Conventions

## Applying `OrgScope` to a Model

Trait only on models owning `org_id` column directly (see "Directly org-scoped"
list in `tenancy-architecture` — today: `Course`, `InvitationLink`,
`ForumTopic`, `HelpArticle`, `AuditLog`). Never on `User` or `Credential`:
identity stays queryable across organizations and the credential's org
target comes explicitly from the host context (`Credential::scopeForOrg()`).
Never on cascade-inherited models (`Module`, `Lesson`, `Quiz`, ...) — they
inherit tenant boundary through parent relation and policy.

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
$table->unsignedBigInteger('org_id')->nullable(); // nullable only for help_articles, audit_logs — never system_settings (non-nullable, default(0) GLOBAL_ORG_ID sentinel, composite PK (setting_key, org_id))
$table->foreign('org_id')->references('id')->on('organizations')->restrictOnDelete(); // or ->cascadeOnDelete() for tables whose rows the org owns outright
$table->index('org_id');
```

Use `restrictOnDelete()`, not `cascadeOnDelete()`, wherever a hard delete must
be blocked — most of all `credentials.org_id`, so an Organization with
existing accounts is never hard-deleted under them. Only soft-delete
(`deleted_at`) available for Organizations in that state. (The old
`users.org_id` column no longer exists: `users` is the global person row;
per-org accounts live in `credentials` —
`2026_08_01_000003_create_credentials_table.php`, whose `user_id` is
`cascadeOnDelete()`.)

Never edit pre-existing base `0001_01_01_000000_create_users_table.php`
migration. Add new separate migration that alters a table. Untouched
original Laravel migration preserve fresh-install history.

## Resolving the Tenant Context in Controllers/Requests

The tenant context is server-resolved, never request input. Two access
paths, both mirroring `OrgScope`'s creating hook order:

- Models/queries: `OrgContext::current()` (`App\Services\OrgContext`) —
  Admin impersonation (`session('active_org_id')`) is applied inside
  `OrgScope` itself; for a non-Admin the host org comes from the bound
  context. Never re-derive with ad-hoc `??` chains.
- Controllers acting on behalf of the org: the shared trait
  `App\Http\Controllers\Concerns\ResolvesOrgContext::resolveOrgId()` —
  Admin → `session('active_org_id')`, others →
  `OrgContext::current()->orgId()`; throws `UnresolvedOrgContextException`
  when neither resolves (Admin browsing a portal host without
  impersonation has no org context). `UserController`,
  `UserImportController`, `GestorStudentController` and
  `GestorProfessorController` share it; new org-acting controllers reuse
  the trait instead of duplicating the chain, so the exception message
  shape and the global handler stay in sync.

## Creating Org-Scoped Rows With No Request In Flight

`OrgScope::booted()`'s creating hook throws `UnresolvedOrgContextException`
when no org resolves **inside a real request** (state-zero write attempt).
Outside a request the context is not authoritative:
`OrgContext::isBound()` is `false` on console commands, queued jobs and
test factories, and the hook then tolerates an explicitly provided
`org_id` instead of throwing (docblock in
`app/Models/Traits/OrgScope.php`). Convention: console/queue code sets
`org_id` explicitly; it must never rely on host context, and must not
silently default one either.

## Handling `UnresolvedOrgContextException`

Exception (`App\Exceptions\UnresolvedOrgContextException extends
RuntimeException`) raised by `OrgScope::booted()` and by
`ResolvesOrgContext::resolveOrgId()`. Must never surface as raw 500.
Register once, globally, in `bootstrap/app.php` exception handling. Never
catch locally in individual controllers:

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
and AJAX/`X-Requested-With`): JSON/AJAX callers get a 422 JSON body, web
callers get a redirect-back (302) with a flashed error message. Never let this
exception fall through to default error page.

## Roles: Gate/Middleware Convention

Authorize by role via Spatie `role:` middleware, matched against `RolesEnum`
values. Never hardcode role string second time:

```php
Route::middleware('role:' . RolesEnum::GESTOR->value)->group(function () {
    // gestor-only routes
});
```

Never enable `config('permission.teams')`. Roles are global; org
partitioning is `org_id` + `OrgScope`, never Spatie team-scoped
permissions. Mixing two give every org-scoped table two independent,
easy-to-desync tenancy mechanisms.

## Factories & Tests

Factories for org-scoped models: always set `org_id` explicit via the
factory's `inOrg()` state (`Course::factory()->inOrg($org)`) or
`->for(Organization::factory())`, never leave to `OrgScope::booted()`
auto-assignment. Tests must be explicit which org they build data for,
and factories often run outside authenticated context. Org membership of
a *person* is `User::factory()->inOrg($org)` (creates the `credentials`
row) — never write `credentials` by hand in tests when the state exists.

```php
Course::factory()->inOrg(Organization::factory()->create())->create();
User::factory()->aluno()->inOrg($org)->withPassword('senha')->create();
```

Polymorphic-ish "FK without real foreign key" columns
(`course_completion_rules.target_id`,
`forum_post_edits`/`forum_reports` `postable_type`/`postable_id`): no DB
constraint to lean on. Validate referenced record existence and its org
membership explicit in Form Request or service layer.
