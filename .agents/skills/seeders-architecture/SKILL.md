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
---

# Database Seeders Architecture

## Overview

Seeder layer = modular, environment-aware seeding pipeline for multi-tenant EAD platform. Seeding must adapt to environment (`production` vs `local`/`testing`/`staging`), stay idempotent on re-run, respect `org_id` isolation.

## Environment Orchestration (`DatabaseSeeder`)

`DatabaseSeeder` = environment dispatcher:

1. **Baseline seeders (all environments):**
   - `RolesAndPermissionsSeeder`: 3 Spatie roles (`admin`, `gestor`, `aluno`) + default permissions.
   - `AdminSeeder`: global Super Admin (`admin@plataforma.com` or `ADMIN_EMAIL` env).
   - `SystemSettingSeeder`: global settings (`org_id = 0` sentinel).
   - `HelpArticleSeeder`: Help Center articles.

2. **Production gate (`App::environment('production')`):**
   - In `production`, stop right after baseline seeders.
   - Blocks fake organizations, test users, demo courses in production.

3. **Non-production pipeline (minimal dev scenario, "Liga Certo"):**
   - `OrganizationSeeder`: single organization `liga-certo`.
   - `UserSeeder`: one organizer (gestor) + one student (aluno), both verified, password `password`.
   - `CourseSeeder`: single course "Curso de Eletricista" with three modules — text lesson + essay quiz, PDF lesson + auto-graded quiz, video lesson + final quiz — plus the student enrollment and the completion rules (`all_lessons` @ 100% + `min_quiz_score` @ 70% on the final quiz). Quizzes/questions are seeded inline by `CourseSeeder`, not by a separate seeder.

## Core Rules

- **Idempotency:** all seeders use `firstOrCreate` / `updateOrCreate` keyed on natural identifiers (`email`, `setting_key` + `org_id`, `name`, `slug`). Re-run `php artisan db:seed` = same DB state.
- **Multitenant:** global settings use `org_id = 0` (`SystemSetting::GLOBAL_ORG_ID`). Domain entities pass `org_id` explicitly — no global scope resolution during seeding (`Course` reads through `withoutGlobalScopes()`).
- **Event suppression:** use the `WithoutModelEvents` trait on the seeder (suspends every model event for the whole `run()`, including nested `$this->call()`s) or `Model::withoutEvents()`. No real mail, no audit log pollution.
- **Seeded binary assets:** the PDF handout is generated at seed time (minimal valid PDF written to the `public` disk) instead of shipping a binary in the repository.
