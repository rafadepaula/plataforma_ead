---
name: seeders-architecture
description: >
  Seeder architecture. DatabaseSeeder environment dispatch,
  RolesAndPermissionsSeeder, SystemSettingSeeder, AdminSeeder. How
  app()->environment('production') block test data. Idempotency via
  firstOrCreate/updateOrCreate. Explicit org_id keep tenant integrity.
license: MIT
metadata:
  feature: database-seeders
  role: architecture
  specs:
    - spec/specs/16-database-seeders-and-environment-seeding.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Database Seeders Architecture

## Overview

SPEC-16 = modular, environment-aware seeding pipeline for multi-tenant EAD platform. Seeding must adapt to environment (`production` vs `local`/`testing`/`staging`), stay idempotent on re-run, respect `org_id` isolation.

## Environment Orchestration (`DatabaseSeeder`)

`DatabaseSeeder` = environment dispatcher:

1. **Baseline seeders (all environments):**
   - `RolesAndPermissionsSeeder`: 3 Spatie roles (`admin`, `gestor`, `aluno`) + default permissions.
   - `AdminSeeder`: global Super Admin (`admin@plataforma.com` or `ADMIN_EMAIL` env).
   - `SystemSettingSeeder`: global settings (`org_id = 0` sentinel).

2. **Production gate (`App::environment('production')`):**
   - In `production`, stop right after baseline seeders.
   - Blocks fake organizations, test users, demo courses in production.

3. **Non-production pipeline:**
   - Runs domain seeders in order when present: `OrganizationSeeder`, `UserSeeder`, `CourseSeeder`, `QuizSeeder`, `InvitationSeeder`, `CertificateSeeder`, `ForumSeeder`, `NotificationSeeder`.

## Core Rules

- **Idempotency:** all seeders use `firstOrCreate` / `updateOrCreate` keyed on natural identifiers (`email`, `setting_key` + `org_id`, `name`, `slug`). Re-run `php artisan db:seed` = same DB state.
- **Multitenant:** global settings use `org_id = 0` (`SystemSetting::GLOBAL_ORG_ID`). Domain entities pass `org_id` explicitly — no global scope resolution during seeding.
- **Event suppression:** mass generation uses `Model::withoutEvents()` or `Notification::fake()`. No real mail, no audit log pollution.
