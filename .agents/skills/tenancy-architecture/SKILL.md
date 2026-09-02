---
name: tenancy-architecture
description: >
  Single-Database multitenancy architecture of Plataforma EAD
  (Organizations / `org_id`, OrgScope global scope, Impersonate Org,
  RolesEnum). Use when you need how tenant isolation works, which tables
  org-scoped, how Admin/Gestor/Aluno roles interact with `org_id`, or
  before designing new table/feature that must respect tenant boundaries.
license: MIT
metadata:
  feature: tenancy
  role: architecture
---

# Tenancy Architecture

## Overview

Platform is **multitenant on single MySQL/MariaDB database** (chosen for
shared-hosting compatibility — no per-tenant database, no subdomain
routing). Each tenant is **Organization** (`organizations` table). Domain
tables carry `org_id` foreign key. Isolation enforced application-side by
`OrgScope` Eloquent trait, not database row-level security.

Deliberate departure from `spatie/laravel-multitenancy`: tenant
resolution here is **not** by domain/subdomain/header, but by
**authenticated user session** (`$user->org_id` or
`session('active_org_id')` for Admin Impersonate Org).

## The Three Roles (`RolesEnum`)

Defined in `App\Enums\Permissions\RolesEnum` (backed string enum:
`admin`, `gestor`, `aluno`), enforced via `spatie/laravel-permission`
roles (not Spatie own "teams"/org feature — `config('permission.teams')`
stays `false`; org partitioning done exclusively through `org_id` +
`OrgScope`, never through Spatie team IDs).

| Role | `org_id` | Scope of data access |
| --- | --- | --- |
| `admin` | always `null` | Global by default. Can narrow to one Organization via **Impersonate Org** (`session('active_org_id')`). |
| `gestor` | fixed to one Organization | Everything under `OrgScope` is automatically restricted to their `org_id`. |
| `aluno` | usually `null` | Not restricted by `OrgScope` on their own account; enrolls in courses across multiple Organizations via `course_user`. Course/classroom context resolves the Org from `courses.org_id`, not from the student's own row. |

**Do not** apply `OrgScope` to `User` model itself. Admin and Aluno rows
legitimately have `org_id = null` and must stay queryable across
organizations (e.g. during login, admin user management). `OrgScope` is
for *domain* tables (courses, modules, lessons, invitation_links,
forum_topics, ...), not for `users` table.

## Data Model — Org-Scoped vs Cascade-Inherited Tables

**Directly org-scoped** (own `org_id` column, `OrgScope` trait applied):
`courses`, `invitation_links`, `forum_topics`, `help_articles` (nullable
— global or org-specific), `system_settings` (nullable — global or
org-specific).

**Cascade-inherited** (no own `org_id`; org implied by parent FK,
`OrgScope` not applied directly — scope through parent relation instead):
`modules` → `courses.org_id`, `lessons` → `modules` → `courses.org_id`,
`quizzes`/`quiz_questions`/`quiz_options` → `lessons` → ... →
`courses.org_id`, `certificates` → `course_id`/`user_id`, `course_user`
(pivot — intentionally NOT org-scoped, since it is how student enrolls
across multiple orgs), `forum_replies` → `forum_topics.org_id`.

**Never org-scoped**: `users` (see above), `notifications` (polymorphic
`notifiable`, org implied by notifiable user).

**Pseudo-polymorphic, no `org_id`, no FK at all** (integrity validated at
application layer only — not to be confused with cascade-inherited tables
above, which do have real parent FK): `forum_post_edits` and
`forum_reports` both carry `postable_type`/`postable_id`
pointing at `ForumTopic`/`ForumReply` written as model FQCN, with no
database foreign key on the pair; resolved exclusively via
`$type::withTrashed()->find($id)` (see `forum-architecture`).
`course_completion_rules.target_id` is same pattern one column
deep, pointing at `modules.id`/`quizzes.id` depending on `rule_type` (see
`certificates-architecture`).

This skill does not repeat column types, only tenancy shape.

## `OrgScope` Trait — How It Behaves

Applied to org-scoped Eloquent models. Two responsibilities:

1. **Global scope (`bootOrgScope`)** — filters every query on model to
   resolved org automatically:
   - No authenticated user (public routes, e.g. certificate validation,
     invitation redemption): no filter applied.
   - `admin` role: filters by `session('active_org_id')` only if
     Impersonate Org active; otherwise sees all organizations.
   - Any other user with `org_id` set (`gestor`): filtered to their
     `org_id`.
   - Any other user **without** `org_id` (e.g. `aluno` querying
     org-scoped model directly, which should not normally happen): scope
     forces `whereRaw('1 = 0')` — safety fallback, not feature. If
     legitimate flow needs `aluno` to read org-scoped model, it must
     resolve org through course/enrollment relation and query that
     relation directly, never rely on global scope defaulting open.

2. **`org_id` auto-assignment (`booted`/`creating`)** — when creating
   model without explicit `org_id`, resolves it from `$user->org_id ??
   session('active_org_id')`. If neither resolves (e.g. Admin with no
   Impersonate Org active creating org-scoped record), it throws
   `UnresolvedOrgContextException` rather than silently persisting
   `org_id = null`. This exception is mapped globally (see
   `tenancy-conventions` skill) to HTTP 422 response.

## Impersonate Org

Admin (`org_id = null`) can temporarily scope own session to one
Organization by setting `session(['active_org_id' => $orgId])`. Plain
session flag, not package feature — no `Tenant::makeCurrent()` call.
Clearing session key (or logging out) returns Admin to global, unscoped
view.

## Related

- `skill-autoupdate` — the auto-update protocol these skills are subject
  to.
