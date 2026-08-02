---
name: certificates-architecture
description: >
  Explains the Certificates & Public Verification domain (SPEC-09): the
  certificates/course_completion_rules schema, the cascade-inherited
  (no `OrgScope`) tenancy of both tables, the `IssueCertificateAction`
  eligibility engine (AND across all 3 `rule_type`s), the SHA-256
  validation-hash formula, and why revocation is a logical, terminal,
  never-soft-deleted state. Use whenever designing or reviewing a feature
  that touches `Certificate`/`CourseCompletionRule` data, before adding a
  new `rule_type`, or when deciding how the public verification route or
  the PDF/QR pipeline should be scoped.
license: MIT
metadata:
  feature: certificates
  role: architecture
  specs:
    - spec/specs/09-certificates-and-public-verification.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Certificates Architecture

## Overview

RF10 lets a Gestor configure `course_completion_rules` on a Course. RF16
requires the resulting certificate to be a branded PDF with an embedded
QR code. RF17 exposes a fully public, unauthenticated, cross-tenant
`/validar-certificado/{hash}` route — the one deliberate crack in this
platform's otherwise strict per-Organization tenancy, because a
certificate must be verifiable by anyone (an employer, another school)
who was never a user of the issuing Organization. RF25 adds Gestor/Admin
revocation. None of this feature ever mutates `courses`/`modules`/
`lessons`/`course_user` — it only *reads* them (plus `quiz_attempts` for
`min_quiz_score` rules) and writes `certificates` rows.

## Schema (SPEC-00 §2.1.14–2.1.15)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `certificates` | `user_id`, `course_id` (**UNIQUE pair**), `validation_hash` (char(64), UNIQUE), `issued_at`, `revoked_at` (nullable), `revoked_by` (nullable FK→users, nullOnDelete), `revoke_reason` (nullable, ≤500 chars) | **Cascade-inherited** via `course_id` → `courses.org_id` — no own `org_id`, no `OrgScope` (see `Certificate`'s own docblock) |
| `course_completion_rules` | `course_id`, `rule_type` (enum: `all_lessons`\|`min_quiz_score`\|`specific_module`, default `all_lessons`), `target_id` (nullable, **no FK** — pseudo-polymorphic pointer to `modules.id` or `quizzes.id` depending on `rule_type`), `required_percentage` (tinyint, default 100) | Cascade-inherited via `course_id` |

Both `user_id`/`course_id` on `certificates` are `restrictOnDelete()` — a
User or Course can never be hard-deleted out from under an issued
certificate; the audit trail is permanent by design. `certificates` has
**no `SoftDeletes`** — a certificate row is never deleted at all, only
logically revoked (`revoked_at`/`revoked_by`/`revoke_reason`). See
`Certificate::isRevoked()`.

## The Eligibility Engine (`IssueCertificateAction`)

Triggered by a Listener on `CourseCompletedByStudent` (SPEC-07 §1.2, fired
by `RecalculateCourseProgress` when `course_user.status` transitions to
`completed`), executed **synchronously** in the same request
(`QUEUE_CONNECTION=sync`, no job) — the whole eligibility check and
`firstOrCreate()` write happen before the HTTP response returns.

For a course with **zero** `course_completion_rules` rows, the Action is a
no-op — no certificate is issued (in practice this rarely fires, because
`RecalculateCourseProgress` itself requires an `all_lessons` rule to ever
mark the enrollment `completed`, but the Action must not assume that
invariant and must guard it defensively).

When rules exist, **every** row must pass (AND, never OR):

| `rule_type` | Eligible when |
| --- | --- |
| `all_lessons` | `course_user.progress_percentage >= required_percentage` (default 100) |
| `min_quiz_score` | `User::bestQuizScoreFor(Quiz::find($rule->target_id))` (SPEC-08's best-attempt lookup, reused, never re-derived) `>= required_percentage` |
| `specific_module` | every `Lesson` of `Module::find($rule->target_id)` has a `LessonProgress.is_completed = true` row for this user |

A `target_id` pointing at a soft-deleted or otherwise missing
Module/Quiz is **not satisfied** (treated as a failing rule), never an
exception — `target_id` has no DB foreign key on purpose (see the
migration's own docblock), so a dangling pointer is an expected runtime
condition, not a bug.

## Idempotency and the Terminal Revoked State

`UNIQUE(user_id, course_id)` is the idempotency mechanism: the Action must
`firstOrCreate(['user_id' => ..., 'course_id' => ...], [...])` (or
equivalent existence-check-then-create) rather than a bare `::create()`,
and must never let the UNIQUE violation surface as an uncaught
`QueryException` — a listener throwing on a re-fired event would be a
500-class bug on an otherwise-benign re-completion. Because the unique
key has no `revoked_at` component, **revocation is a terminal state per
enrollment**: once revoked, the pair can never receive a fresh row, even
if the student re-completes the course after a reopened attempt. This is
an explicit, current-version limitation (see SPEC-09 §1.1's own note) —
do not "fix" it by adding a new row or by un-revoking on re-completion
without a deliberate, separately-specified migration.

## The Public Verification Route Is the One Deliberately Unscoped Boundary

`GET /validar-certificado/{hash}` (`certificates.verify`) carries **no**
middleware at all — not `auth`, not `guest`, not `role:*` — because it
must resolve identically for an anonymous visitor and an already
-logged-in Admin/Gestor/Aluno of a *different* Organization. Both
`PublicCertificateController` and `CertificatePdfService` must resolve
`$certificate->course->organization` with
`Course::withoutGlobalScopes()`/an explicit un-scoped query — the normal
`OrgScope` on `Course` would otherwise silently filter out a Course
belonging to an Org the current viewer (if any) isn't scoped to. See
`tenancy-architecture` for the general cascade-inherited-model rule this
route is the one deliberate, spec-mandated exception to.

A hash that never existed 404s. A hash that resolves to a **revoked**
certificate must still return `200 OK` — public auditability of a
revocation is intentional (SPEC-09 §2), so `PublicCertificateController`
must distinguish "row not found" (404) from "row found, `revoked_at` set"
(200, revoked-state view) as two genuinely different code paths, never
conflate them.

## Authorization Boundary

`CertificatePolicy::revoke()` is the **only** enforcement point for the
Gestor/Admin certificate list and revoke action — `Certificate` and
`CourseCompletionRule` intentionally carry no `OrgScope`
(cascade-inherited only), so `CertificateController::index()`/`revoke()`
must authorize explicitly rather than relying on a query scope to filter
cross-org rows out. `role:admin` is unrestricted; `role:gestor` only when
`$certificate->course->org_id === $user->org_id` (course loaded
`withoutGlobalScopes()` for the comparison, same reasoning as above).
