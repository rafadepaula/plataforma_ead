---
name: seeders-maintenance
description: >
  Debug, test, idempotency guide for Database Seeders: mandatory
  PHPUnit tests (`DatabaseSeederProductionTest`, `DatabaseSeederDevelopmentTest`,
  `SeederIdempotencyTest`), duplicate key fixes via firstOrCreate/updateOrCreate,
  event/notification suppression, org_id context preservation.
license: MIT
metadata:
  feature: seeders
  role: maintenance
---

# Seeders Maintenance

## Mandatory Test Coverage

These PHPUnit tests guard the seeder contract. Keep green:

- `tests/Feature/Seeders/DatabaseSeederDevelopmentTest.php` — seeding in local/development/testing creates the minimal dev scenario ("Liga Certo" organization, gestor + aluno, one "Curso de Eletricista" with three modules and quizzes, enrollment, completion rules) with explicit `org_id`, no mail/events leak.
- `tests/Feature/Seeders/SeederIdempotencyTest.php` — `php artisan db:seed` run many times: no duplicate key exception, table counts identical.

Run:

```bash
vendor/bin/sail artisan test --filter=DatabaseSeederDevelopmentTest
```

## Failure Modes

- **Duplicate key / unique constraint `QueryException` on re-run:**
  Seeder used bare `Model::create()` or raw insert. Switch to `firstOrCreate`/`updateOrCreate` keyed on unique natural key (`token`, `validation_hash`, `email`, `slug`, `id`).

- **`UnresolvedOrgContextException` while seeding:**
  `OrgScope` models (`Course`, `HelpArticle`, `SystemSetting`) have no HTTP session. Pass `org_id` explicitly, or wrap in `withoutEvents()`.

- **Unwanted mail / event side effects:**
  Use the `WithoutModelEvents` trait on the seeder (or `Model::withoutEvents(...)`), `Mail::fake()`, `Notification::fake()` inside seeder or test setup.
