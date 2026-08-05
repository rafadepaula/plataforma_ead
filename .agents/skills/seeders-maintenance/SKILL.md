---
name: seeders-maintenance
description: >
  Debugging, testing, and idempotency guide for Database Seeders (SPEC-16):
  the mandatory PHPUnit tests (`DatabaseSeederProductionTest`, `DatabaseSeederDevelopmentTest`, `SeederIdempotencyTest`),
  preventing duplicate key errors via firstOrCreate/updateOrCreate,
  event and notification suppression during seeding, and multitenant org_id context preservation.
license: MIT
metadata:
  feature: seeders
  role: maintenance
  specs:
    - spec/specs/16-database-seeders-and-environment-seeding.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Seeders Maintenance

## Mandatory Test Coverage for Database Seeders

These PHPUnit tests guard the SPEC-16 database seeding contract and must remain green:

- `tests/Feature/Seeders/DatabaseSeederDevelopmentTest.php` — verifies that running database seeders in local/development/testing environment creates all expected records across entities (Organisations, Users, Courses, Quizzes, Invitations, Certificates, Forum, Notifications) with explicit `org_id` binding and suppresses unwanted mail/events.
- `tests/Feature/Seeders/SeederIdempotencyTest.php` — verifies that executing `php artisan db:seed` multiple consecutive times runs cleanly without throwing duplicate key exceptions and leaves table counts identical.

Run the seeder test suite using Sail:

```bash
vendor/bin/sail artisan test --filter=DatabaseSeederDevelopmentTest
```

## Common Failure Modes & Troubleshooting

- **Duplicate Key / Unique Constraint `QueryException` on re-run:**
  Every seeder must use `firstOrCreate` or `updateOrCreate` with unique natural keys (e.g. `token`, `validation_hash`, `email`, `slug`, `id`). Avoid bare `Model::create()` or raw `DB::table()->insert()` calls without uniqueness checks.

- **`UnresolvedOrgContextException` during Seeding:**
  Models using `OrgScope` (`InvitationLink`, `ForumTopic`, `Course`, `HelpArticle`, `SystemSetting`) require an explicit `org_id` parameter during model instantiation or `withoutEvents()` wrappers when running outside an HTTP session. Always explicitly supply `org_id` when seeding tenant-scoped records.

- **Unwanted Mail / Event Side Effects during Seeding:**
  Use `Model::withoutEvents(...)`, `Mail::fake()`, or `Notification::fake()` within seeders or test setups to prevent actual emails, Webhooks, or heavy audit log creation during seeding.

- **DatabaseNotification Missing/Duplicated:**
  Use deterministic UUIDs (e.g., formatted based on user ID) when seeding `DatabaseNotification` rows via `firstOrCreate(['id' => $uuid], [...])`.
