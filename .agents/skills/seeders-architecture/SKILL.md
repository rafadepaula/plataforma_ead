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
    - `RolesAndPermissionsSeeder`: 4 Spatie roles (`admin`, `gestor`, `aluno`, `professor`, via `RolesEnum::cases()`) + default permissions.
    - `AdminSeeder`: global Super Admin (`config('app.admin_email', 'admin@plataforma.com')`, `AdminSeeder.php:21`).
   - `SystemSettingSeeder`: global settings (`org_id = 0` sentinel).
   - `HelpArticleSeeder`: Help Center articles.

2. **Production gate (`App::environment('production')`):**
   - In `production`, stop right after baseline seeders.
   - Blocks fake organizations, test users, demo courses in production.

3. **Non-production pipeline (minimal dev scenario, "Liga Certo"):**
   - `OrganizationSeeder`: single organization `liga-certo`.
    - `UserSeeder`: 3 accounts — one gestor + one aluno + one professor (`professor.ligacerto@plataforma.com`, `UserSeeder.php:62-77`), all verified, password `password`.
   - `CourseSeeder`: single course "Curso de Eletricista" with three modules — text lesson + essay quiz, PDF lesson + auto-graded quiz, video lesson + final quiz — plus the student enrollment and the completion rules (`all_lessons` @ 100% + `min_quiz_score` @ 70% on the final quiz). Quizzes/questions are seeded inline by `CourseSeeder`, not by a separate seeder.

## Core Rules

- **Idempotency:** all seeders use `firstOrCreate` / `updateOrCreate` keyed on natural identifiers (`email`, `setting_key` + `org_id`, `name`, `slug`). Re-run `php artisan db:seed` = same DB state.
- **Multitenant:** global settings use `org_id = 0` (`SystemSetting::GLOBAL_ORG_ID`). Domain entities pass `org_id` explicitly — no global scope resolution during seeding (`Course` reads through `withoutGlobalScopes()`).
- **Event suppression:** use the `WithoutModelEvents` trait on the seeder (suspends every model event for the whole `run()`, including nested `$this->call()`s) or `Model::withoutEvents()`. No real mail, no audit log pollution.
- **Seeded binary assets:** PDFs generated at seed time on the private `local` disk (`CourseSeeder.php:24-35`), served only through the gated `lessons.pdf.show` route, never via `/storage` — no binary shipped in repo.
