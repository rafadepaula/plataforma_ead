---
name: tenancy-architecture
description: >
  Explains the Single-Database multitenancy architecture of the Plataforma EAD
  (Organizations / `org_id`, OrgScope global scope, Impersonate Org, RolesEnum).
  Use whenever you need to understand how tenant isolation works, which tables
  are org-scoped, how the Admin/Gestor/Aluno roles interact with `org_id`, or
  before designing a new table/feature that must respect tenant boundaries.
license: MIT
metadata:
  feature: tenancy
  role: architecture
  specs:
    - spec/specs/00-architecture-database-and-guardrails.md
    - spec/docs/multitenancy.md
---

# Tenancy Architecture

## Overview

The platform is **multitenant on a single MySQL/MariaDB database** (chosen for
shared-hosting compatibility — no per-tenant database, no subdomain routing).
Each tenant is an **Organization** (`organizations` table). Domain tables carry
an `org_id` foreign key, and isolation is enforced application-side by the
`OrgScope` Eloquent trait rather than database row-level security.

This is a deliberate departure from `spatie/laravel-multitenancy`: tenant
resolution here is **not** by domain/subdomain/header, but by the
**authenticated user's session** (`$user->org_id` or `session('active_org_id')`
for an Admin's Impersonate Org). See `spec/docs/multitenancy.md` §1 for the
full comparison and rationale.

## The Three Roles (`RolesEnum`)

Defined in `App\Enums\Permissions\RolesEnum` (backed string enum: `admin`,
`gestor`, `aluno`), enforced via `spatie/laravel-permission` roles (not
Spatie's own "teams"/org feature — `config('permission.teams')` stays `false`;
org partitioning is done exclusively through `org_id` + `OrgScope`, never
through Spatie team IDs).

| Role | `org_id` | Scope of data access |
| --- | --- | --- |
| `admin` | always `null` | Global by default. Can narrow to one Organization via **Impersonate Org** (`session('active_org_id')`). |
| `gestor` | fixed to one Organization | Everything under `OrgScope` is automatically restricted to their `org_id`. |
| `aluno` | usually `null` | Not restricted by `OrgScope` on their own account; enrolls in courses across multiple Organizations via `course_user`. Course/classroom context resolves the Org from `courses.org_id`, not from the student's own row. |

**Do not** apply `OrgScope` to the `User` model itself. Admin and Aluno rows
legitimately have `org_id = null` and must remain queryable across
organizations (e.g. during login, admin user management). `OrgScope` is for
*domain* tables (courses, modules, lessons, invitation_links, forum_topics,
...), not for the `users` table.

## Data Model — Org-Scoped vs. Cascade-Inherited Tables

**Directly org-scoped** (own `org_id` column, `OrgScope` trait applied):
`courses`, `invitation_links`, `forum_topics`, `help_articles` (nullable —
global or org-specific), `system_settings` (nullable — global or org-specific).

**Cascade-inherited** (no own `org_id`; org is implied by parent FK, `OrgScope`
not applied directly — scope through the parent relation instead):
`modules` → `courses.org_id`, `lessons` → `modules` → `courses.org_id`,
`quizzes`/`quiz_questions`/`quiz_options` → `lessons` → ... → `courses.org_id`,
`certificates` → `course_id`/`user_id`, `course_user` (pivot — intentionally
NOT org-scoped, since it is how a student enrolls across multiple orgs),
`forum_replies` → `forum_topics.org_id`.

**Never org-scoped**: `users` (see above), `notifications` (polymorphic
`notifiable`, org implied by the notifiable user).

**Pseudo-polymorphic, no `org_id`, no FK at all** (integrity validated at
the application layer only — not to be confused with cascade-inherited
tables above, which do have a real parent FK): `forum_post_edits` and
`forum_reports` (SPEC-10) both carry `postable_type`/`postable_id`
pointing at a `ForumTopic`/`ForumReply` written as the model's FQCN, with
no database foreign key on the pair; resolved exclusively via
`$type::withTrashed()->find($id)` (see `forum-architecture`).
`course_completion_rules.target_id` (SPEC-09) is the same pattern one
column deep, pointing at `modules.id`/`quizzes.id` depending on
`rule_type` (see `certificates-architecture`).

Full column/type/index/`onDelete` definitions live in
`spec/specs/00-architecture-database-and-guardrails.md` §2.1 — this skill does
not repeat column types, only the tenancy shape.

## `OrgScope` Trait — How It Behaves

Applied to org-scoped Eloquent models. Two responsibilities:

1. **Global scope (`bootOrgScope`)** — automatically filters every query on the
   model to the resolved org:
   - No authenticated user (public routes, e.g. certificate validation,
     invitation redemption): no filter applied.
   - `admin` role: filters by `session('active_org_id')` only if Impersonate
     Org is active; otherwise sees all organizations.
   - Any other user with `org_id` set (`gestor`): filtered to their `org_id`.
   - Any other user **without** `org_id` (e.g. an `aluno` querying an
     org-scoped model directly, which should not normally happen): the scope
     forces `whereRaw('1 = 0')` — a safety fallback, not a feature. If a
     legitimate flow needs an `aluno` to read an org-scoped model, it must
     resolve the org through the course/enrollment relation and query that
     relation directly, not rely on the global scope defaulting open.

2. **`org_id` auto-assignment (`booted`/`creating`)** — when creating a model
   without an explicit `org_id`, resolves it from `$user->org_id ??
   session('active_org_id')`. If neither resolves (e.g. an Admin with no
   Impersonate Org active creating an org-scoped record), it throws
   `UnresolvedOrgContextException` rather than silently persisting
   `org_id = null`. This exception is mapped globally (see
   `tenancy-conventions` skill) to an HTTP 422 response.

The canonical trait implementation is reproduced in
`spec/specs/00-architecture-database-and-guardrails.md` §3 and
`spec/docs/multitenancy.md` §4 — they must stay byte-identical; if you change
the trait, update both docs (see `tenancy-maintenance`).

## Impersonate Org

An Admin (`org_id = null`) can temporarily scope their own session to one
Organization by setting `session(['active_org_id' => $orgId])`. This is a
plain session flag, not a package feature — there is no `Tenant::makeCurrent()`
call. Clearing the session key (or logging out) returns the Admin to the
global, unscoped view.

## Related Specs

- `spec/specs/00-architecture-database-and-guardrails.md` — full schema (22
  tables), `OrgScope` source, `RolesEnum` source, quality guardrails.
- `spec/specs/03-agentic-harness-and-self-updating-skills.md` — why these
  skills exist and the auto-update protocol they're subject to.
- `spec/docs/multitenancy.md` — architecture decision record / comparison
  against `spatie/laravel-multitenancy`.
