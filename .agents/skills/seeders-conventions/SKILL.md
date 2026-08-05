---
name: seeders-conventions
description: >
  Concrete code patterns, idempotency guidelines, event suppression rules, and
  environment isolation standards for database seeders in Plataforma EAD. Use whenever
  writing or updating Laravel Seeder classes in database/seeders/.
license: MIT
metadata:
  feature: seeders
  role: conventions
  specs:
    - spec/specs/16-database-seeders-and-environment-seeding.md
---

# Seeders Conventions

## Environment Isolation in DatabaseSeeder

`DatabaseSeeder` MUST check `app()->environment('production')`. In production, only production-safe seeders (`RolesAndPermissionsSeeder`, `SystemSettingSeeder`, `AdminSeeder`) run. In non-production environments (`local`, `staging`, `testing`), the full suite of domain seeders runs.

```php
if (app()->environment('production')) {
    return;
}
```

## Idempotency via `firstOrCreate` and `updateOrCreate`

Seeders MUST NEVER use `Model::create()` or raw `DB::table()->insert()` without checking existence. Every seeder call MUST use `firstOrCreate` or `updateOrCreate` keyed on natural unique identifiers (such as `email`, `slug`, `title`, or foreign key relationships).

```php
$org = Organization::firstOrCreate(
    ['slug' => 'acme-cursos'],
    [
        'name' => 'Acme Cursos',
        'cnpj' => '12.345.678/0001-90',
        'status' => 'active',
    ]
);
```

## Event Suppression (`Model::withoutEvents`)

To prevent unwanted side-effects during seeding—such as sending real notifications, triggering audit log listeners (`AuditableTrait`), or dispatching email alerts—wrap Eloquent creation in `withoutEvents`:

```php
User::withoutEvents(function () use ($acme): void {
    User::firstOrCreate(
        ['email' => 'gestor.acme@plataforma.com'],
        [
            'name' => 'Gestor Acme',
            'password' => Hash::make('password'),
            'org_id' => $acme->id,
            'status' => 'active',
        ]
    );
});
```

## Multitenant Context & Explicit `org_id`

Models protected by `OrgScope` or direct tenant association MUST receive an explicit `org_id` during seeding. Cascade-inherited models (`Module`, `Lesson`, `Quiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `QuizAnswer`) inherit `org_id` from their parent records.

```php
$course = Course::withoutGlobalScopes()->firstOrCreate(
    ['org_id' => $org->id, 'title' => 'Desenvolvimento Laravel Avançado'],
    [
        'description' => 'Curso completo de Laravel',
        'workload_hours' => 40,
        'is_published' => true,
    ]
);
```

## Spatie Role Assignment

Assign Spatie roles to seeded users only after checking if the role is already assigned, maintaining idempotency across repeated seeder runs:

```php
if (! $user->hasRole(RolesEnum::GESTOR->value)) {
    $user->assignRole(RolesEnum::GESTOR->value);
}
```
