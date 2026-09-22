---
name: seeders-maintenance
description: >
  Database seeders: DatabaseSeeder environment dispatch, RolesAndPermissionsSeeder,
  SystemSettingSeeder, AdminSeeder, app()->environment('production') test-data block,
  idempotency via firstOrCreate/updateOrCreate, event/notification suppression,
  org_id context preservation. Use when writing or editing Seeder classes in
  database/seeders/, or when `DatabaseSeederProductionTest`,
  `DatabaseSeederDevelopmentTest` or `SeederIdempotencyTest` fails, a duplicate-key
  error appears on re-seed, or a seeder fires events/notifications it should not.
license: MIT
metadata:
  feature: seeders
  roles: [architecture, conventions, maintenance]
---

# Database Seeders (`seeders-maintenance`)

Database seeders: `DatabaseSeeder` environment dispatch, RolesAndPermissions/SystemSetting/Admin seeders, production test-data block, idempotency via `firstOrCreate`/`updateOrCreate`, explicit `org_id` keeping tenant integrity.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | How `DatabaseSeeder` dispatches per environment, which system seeders exist, how the production test-data block works, idempotency and tenant-integrity rules. |
| `resource/conventions.md` | Writing or editing Seeder classes in `database/seeders/`. |
| `resource/maintenance.md` | `DatabaseSeederProductionTest`, `DatabaseSeederDevelopmentTest`, or `SeederIdempotencyTest` fails; duplicate keys on re-seed; event/notification side effects; org_id context lost. |
