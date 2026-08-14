---
name: seeders-conventions
description: >
  Code patterns, idempotency rules, event suppression, environment isolation
  for seeders in Plataforma EAD. Use when writing or editing Seeder classes
  in database/seeders/.
license: MIT
metadata:
  feature: seeders
  role: conventions
  specs:
    - spec/specs/16-database-seeders-and-environment-seeding.md
---

# Seeders Conventions

## Environment Isolation in DatabaseSeeder

`DatabaseSeeder` MUST check `app()->environment('production')`. Production runs only safe seeders (`RolesAndPermissionsSeeder`, `SystemSettingSeeder`, `AdminSeeder`). Non-production (`local`, `staging`, `testing`) runs full domain suite.

```php
if (app()->environment('production')) {
    return;
}
```

## Idempotency: `firstOrCreate` / `updateOrCreate`

NEVER bare `Model::create()` or raw `DB::table()->insert()`. Every seeder write uses `firstOrCreate` or `updateOrCreate` keyed on natural unique identifier (`email`, `slug`, `title`, FK pair).

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

Stop side effects while seeding — real notifications, `AuditableTrait` listeners, mail alerts. Wrap creation:

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

## Explicit `org_id`

Models under `OrgScope` or direct tenant link MUST get explicit `org_id` when seeded. Cascade-inherited models (`Module`, `Lesson`, `Quiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `QuizAnswer`) inherit `org_id` from parent.

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

Check before assign — keeps re-runs idempotent:

```php
if (! $user->hasRole(RolesEnum::GESTOR->value)) {
    $user->assignRole(RolesEnum::GESTOR->value);
}
```
