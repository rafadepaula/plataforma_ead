---
name: seeders-architecture
description: >
  Explains the database seeders architecture (DatabaseSeeder environment
  orchestration, RolesAndPermissionsSeeder, SystemSettingSeeder, AdminSeeder),
  how app()->environment('production') gates non-production test data, and how
  idempotency via firstOrCreate/updateOrCreate and explicit org_id preservation
  maintain database integrity across environments.
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

SPEC-16 defines a modular, environment-aware seeding pipeline for the multi-tenant EAD platform. Seeding must strictly adapt to the execution environment (`production` vs non-production environments such as `local`, `testing`, or `staging`), guarantee perfect idempotency on repeated execution, and respect multitenant isolation (`org_id`).

## Environment Orchestration (`DatabaseSeeder`)

`DatabaseSeeder` acts as the environment dispatcher:

1. **Baseline Seeders (All Environments):**
   - `RolesAndPermissionsSeeder`: Initializes the 3 fundamental Spatie roles (`admin`, `gestor`, `aluno`) and default permissions.
   - `AdminSeeder`: Initializes the global Super Admin user (`admin@plataforma.com` or `ADMIN_EMAIL` env).
   - `SystemSettingSeeder`: Initializes global system settings (`org_id = 0` sentinel).

2. **Production Safety Gate (`App::environment('production')`):**
   - If in `production`, execution terminates immediately after running baseline seeders.
   - Prevents accidental generation of fake organizations, test users, or demo courses in production.

3. **Development / Homologation Pipeline (Non-Production):**
   - Sequentially invokes domain seeders when available: `OrganizationSeeder`, `UserSeeder`, `CourseSeeder`, `QuizSeeder`, `InvitationSeeder`, `CertificateSeeder`, `ForumSeeder`, `NotificationSeeder`.

## Core Principles & Rules

- **Idempotency:** All seeders use `firstOrCreate` or `updateOrCreate` keyed on natural identifiers (`email`, `setting_key` + `org_id`, `name`, `slug`). Running `php artisan db:seed` multiple times is safe and produces identical database state.
- **Multitenant Awareness:** Global settings use `org_id = 0` (`SystemSetting::GLOBAL_ORG_ID`). Domain entities explicitly specify `org_id` to bypass global scope resolution issues during database population.
- **Event Suppression:** Mass data generation uses `Model::withoutEvents()` or `Notification::fake()` where applicable to avoid sending real emails or polluting audit log tables.
