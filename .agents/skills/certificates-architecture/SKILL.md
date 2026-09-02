---
name: certificates-architecture
description: >
  Certificates & Public Verification domain:
  certificates/course_completion_rules schema, cascade-inherited (no
  `OrgScope`) tenancy of both tables, `IssueCertificateAction`
  eligibility engine (AND across all 3 `rule_type`s), SHA-256
  validation-hash formula, why revocation is logical, terminal,
  never-soft-deleted state. Use when designing or reviewing feature
  touching `Certificate`/`CourseCompletionRule` data, before adding new
  `rule_type`, or when deciding how public verification route or PDF/QR
  pipeline gets scoped.
license: MIT
metadata:
  feature: certificates
  role: architecture
---

# Certificates Architecture

## Overview

Gestor configures `course_completion_rules` on Course. The resulting
certificate is a branded PDF with embedded QR code. A fully public,
unauthenticated, cross-tenant
`/validar-certificado/{hash?}` route exposes verification — one deliberate
crack in this platform's otherwise strict per-Organization tenancy, because
certificate must be verifiable by anyone (employer, another school) who was
never user of issuing Organization. Gestor/Admin can revoke certificates.
None of
this feature ever mutates `courses`/`modules`/`lessons`/`course_user` — it
only *reads* them (plus `quiz_attempts` for `min_quiz_score` rules) and
writes `certificates` rows.

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `certificates` | `user_id`, `course_id` (**UNIQUE pair**), `validation_hash` (char(64), UNIQUE), `issued_at`, `revoked_at` (nullable), `revoked_by` (nullable FK→users, nullOnDelete), `revoke_reason` (nullable, ≤500 chars) | **Cascade-inherited** via `course_id` → `courses.org_id` — no own `org_id`, no `OrgScope` (see `Certificate`'s own docblock) |
| `course_completion_rules` | `course_id`, `rule_type` (enum: `all_lessons`\|`min_quiz_score`\|`specific_module`, default `all_lessons`), `target_id` (nullable, **no FK** — pseudo-polymorphic pointer to `modules.id` or `quizzes.id` depending on `rule_type`), `required_percentage` (tinyint, default 100) | Cascade-inherited via `course_id` |

Both `user_id`/`course_id` on `certificates` are `restrictOnDelete()` —
User or Course can never be hard-deleted out from under issued
certificate; audit trail permanent by design. `certificates` has **no
`SoftDeletes`** — certificate row never deleted at all, only logically
revoked (`revoked_at`/`revoked_by`/`revoke_reason`). See
`Certificate::isRevoked()`.

## The Eligibility Engine (`IssueCertificateAction`)

Triggered by Listener on `CourseCompletedByStudent` (fired
by `RecalculateCourseProgress` when `course_user.status` transitions to
`completed`), executed **synchronously** in same request
(`QUEUE_CONNECTION=sync`, no job) — whole eligibility check and
`firstOrCreate()` write happen before HTTP response returns.

Course with **zero** `course_completion_rules` rows: Action is no-op, no
certificate issued. In practice rarely fires, because
`RecalculateCourseProgress` itself requires `all_lessons` rule to ever
mark enrollment `completed`, but Action must not assume that invariant,
must guard defensively.

Rules exist: **every** row must pass (AND, never OR):

| `rule_type` | Eligible when |
| --- | --- |
| `all_lessons` | `course_user.progress_percentage >= required_percentage` (default 100) |
| `min_quiz_score` | `User::bestQuizScoreFor(Quiz::find($rule->target_id))` (the quizzes domain's best-attempt lookup, reused, never re-derived) `>= required_percentage` |
| `specific_module` | every `Lesson` of `Module::find($rule->target_id)` has a `LessonProgress.is_completed = true` row for this user |

`target_id` pointing at soft-deleted or missing Module/Quiz is **not
satisfied** (treated as failing rule), never exception — `target_id` has
no DB foreign key on purpose (see migration's own docblock), so dangling
pointer is expected runtime condition, not bug.

## Idempotency and the Terminal Revoked State

`UNIQUE(user_id, course_id)` is idempotency mechanism: Action must
`firstOrCreate(['user_id' => ..., 'course_id' => ...], [...])` (or
equivalent existence-check-then-create) rather than bare `::create()`, and
must never let UNIQUE violation surface as uncaught `QueryException` —
listener throwing on re-fired event would be 500-class bug on
otherwise-benign re-completion. Unique key has no `revoked_at` component,
so **revocation is terminal state per enrollment**: once revoked, pair can
never receive fresh row, even if student re-completes course after
reopened attempt. Explicit, current-version limitation — do not "fix" it
by adding new row or by un-revoking on re-completion without a deliberate,
separately-approved migration.

## The Public Verification Route Is the One Deliberately Unscoped Boundary

`GET /validar-certificado/{hash?}` (`certificates.verify`) carries **no**
middleware at all — not `auth`, not `guest`, not `role:*` — because it
must resolve identically for anonymous visitor and already-logged-in
Admin/Gestor/Aluno of *different* Organization. The `{hash?}` segment is
optional so the same route doubles as the public **entry point** linked
from the Landing Page footer: with no hash (or a blank `?hash=`) the
controller renders the lookup form (`public/certificates/lookup.blade.php`),
which submits the typed hash back as `?hash=…` and re-enters the same
action. A visitor holding a printed certificate has the code, not a URL —
so the hash-less state must never 404, and no route constraint may make
it unreachable. Both
`PublicCertificateController` and `CertificatePdfService` must resolve
`$certificate->course->organization` with
`Course::withoutGlobalScopes()`/explicit un-scoped query — normal
`OrgScope` on `Course` would otherwise silently filter out Course
belonging to Org current viewer (if any) isn't scoped to. See
`tenancy-architecture` for general cascade-inherited-model rule this route
is the one deliberate exception to.

Hash that never existed 404s. Hash resolving to **revoked** certificate
must still return `200 OK` — public auditability of revocation is
intentional, so `PublicCertificateController` must
distinguish "row not found" (404) from "row found, `revoked_at` set"
(200, revoked-state view) as two genuinely different code paths, never
conflate them.

## Authorization Boundary

`CertificatePolicy::revoke()` is **only** enforcement point for
Gestor/Admin certificate list and revoke action — `Certificate` and
`CourseCompletionRule` intentionally carry no `OrgScope`
(cascade-inherited only), so `CertificateController::index()`/`revoke()`
must authorize explicitly rather than rely on query scope to filter
cross-org rows out. `role:admin` unrestricted; `role:gestor` only when
`$certificate->course->org_id === $user->org_id` (course loaded
`withoutGlobalScopes()` for comparison, same reasoning as above).
