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
---

# Seeders Conventions

## Environment Isolation in DatabaseSeeder

`DatabaseSeeder` MUST check `app()->environment('production')`. Production runs only safe seeders (`RolesAndPermissionsSeeder`, `SystemSettingSeeder`, `AdminSeeder`, `HelpArticleSeeder`). Non-production (`local`, `staging`, `testing`) runs the minimal dev scenario (`OrganizationSeeder`, `UserSeeder`, `CourseSeeder`).

```php
if (app()->environment('production')) {
    return;
}
```

## Idempotency: `firstOrCreate` / `updateOrCreate`

NEVER bare `Model::create()` or raw `DB::table()->insert()`. Every seeder write uses `firstOrCreate` or `updateOrCreate` keyed on natural unique identifier (`email`, `slug`, `title`, FK pair).

```php
$org = Organization::firstOrCreate(
    ['slug' => 'liga-certo'],
    [
        'name' => 'Liga Certo',
        'cnpj' => '12.345.678/0001-90',
        'status' => 'active',
    ]
);
```

## Event Suppression (`WithoutModelEvents` trait)

Stop side effects while seeding — real notifications, `AuditableTrait` listeners, mail alerts. Prefer the `WithoutModelEvents` trait on the seeder class: it suspends every model event for the whole `run()`, including nested `$this->call()`s, without nesting `Model::withoutEvents()` per model.

```php
class CourseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // every creation below runs with model events suspended
    }
}
```

`Model::withoutEvents(function () use ($org): void { ... })` remains the alternative for scoping suppression to one block.

## Explicit `org_id`

Models under `OrgScope` or direct tenant link MUST get explicit `org_id` when seeded. Cascade-inherited models (`Module`, `Lesson`, `Quiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `QuizAnswer`) inherit `org_id` from parent.

```php
$course = Course::withoutGlobalScopes()->firstOrCreate(
    ['org_id' => $org->id, 'title' => 'Curso de Eletricista'],
    [
        'description' => 'Formação prática em instalações elétricas residenciais.',
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
