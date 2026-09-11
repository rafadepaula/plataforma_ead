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
    - `AdminSeeder`: global Super Admin (`config('app.admin_email', 'admin@plataforma.com')`, password from `config('app.admin_password', 'admin')`) whose single `credentials` row has **`org_id = null`** — the only account valid on every host, including state zero.
   - `SystemSettingSeeder`: global settings (`org_id = 0` sentinel).
   - `HelpArticleSeeder`: Help Center articles.

2. **Production gate (`App::environment('production')`):**
   - In `production`, stop right after baseline seeders.
   - Blocks fake organizations, test users, demo courses in production.

3. **Non-production pipeline (two demo tenants, host-based tenancy):**
   - `OrganizationSeeder`: the two development Organizations keyed on **`host`** (the exact hosts `scripts/setup-hosts.sh` maps in `/etc/hosts`): `localhost.ligacerto` → "Liga Certo" (`landing_view` = `ligacerto`) and `localhost.informatica` → "Informática Mais" (`landing_view` = `informatica`). Created inside `Organization::withoutEvents(...)`.
    - `UserSeeder`: 5 accounts — gestor + aluno + professor on Liga Certo (`gestor|aluno|professor.ligacerto@plataforma.com`), gestor + aluno on Informática (`gestor|aluno.informatica@plataforma.com`). Each account = global `User` (`firstOrCreate` by e-mail) + `Credential::firstOrCreate` on `(user_id, org_id)` with password `password` — the per-org account IS the credential row (see `tenancy-architecture`).
   - `CourseSeeder`: one course per org — "Curso de Eletricista" on Liga Certo (three modules: text lesson + essay quiz, PDF lessons + auto-graded quiz, video lesson + final quiz; completion rules `all_lessons` @ 100% + `min_quiz_score` @ 70% on the final quiz; the Liga Certo aluno enrolled and the demo professor attached via the `course_professor` pivot) and "Introdução à Informática Básica" on Informática (one module / one content lesson, the demo aluna enrolled). Quizzes/questions are seeded inline by `CourseSeeder`, not by a separate seeder.

## Core Rules

- **Idempotency:** all seeders use `firstOrCreate` / `updateOrCreate` keyed on natural identifiers (`email`, `host`, `setting_key` + `org_id`, `name`, `slug`, `(user_id, org_id)`). Re-run `php artisan db:seed` = same DB state.
- **Multitenant:** global settings use `org_id = 0` (`SystemSetting::GLOBAL_ORG_ID`). Domain entities pass `org_id` explicitly — no global scope resolution during seeding (`Course` reads through `withoutGlobalScopes()`). People are seeded as global `User` + per-org `credentials` row, never a `users.org_id` column.
- **Event suppression:** use the `WithoutModelEvents` trait on the seeder (suspends every model event for the whole `run()`, including nested `$this->call()`s) or `Model::withoutEvents()`. No real mail, no audit log pollution.
- **Seeded binary assets:** PDFs generated at seed time on the private `local` disk (`CourseSeeder.php:24-35`), served only through the gated `lessons.pdf.show` route, never via `/storage` — no binary shipped in repo.
