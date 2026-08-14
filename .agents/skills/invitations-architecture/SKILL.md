---
name: invitations-architecture
description: >
  Smart Invitation & Enrollment domain (SPEC-06): invitation_links schema,
  public unauthenticated /convite/{token} flow, RN09 multi-org
  no-duplicate-account guarantee, RF21 manual enrollment panel reusing
  course_user/CoursePolicy instead of dedicated Enrollment model. Use when
  designing or reviewing feature touching InvitationLink or course_user
  data, before adding new enrollment/invitation endpoint, or when deciding
  how multi-org adaptive registration form behaves.
license: MIT
metadata:
  feature: invitations
  role: architecture
  specs:
    - spec/specs/06-smart-invitation-and-enrollment-system.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Invitations Architecture

## Overview

RF03 gives every Course shareable `/convite/{token}` link. Unauthenticated
visitor self-registers (or authenticates into existing account) and gets
enrolled in that one Course, one step, no admin. RF21 gives Gestor
(`role:gestor`)/Admin manual enroll-or-revoke panel over same `course_user`
rows, for cases with no invite link. RN09 is constraint binding both:
student who already has account (maybe tied to different Organization)
must never get second `users` row just because they used different Org's
invite link.

## Schema (SPEC-00 §2.1, SPEC-06 §2)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `invitation_links` | `org_id`, `token` (64-char, unique), `course_id`, `max_uses`, `current_uses`, `expires_at`, `revoked_at`, `created_by` | **Directly org-scoped** — `OrgScope` trait, same as `Course` |
| `course_user` (pivot) | `user_id`, `course_id`, `status` (`active`\|`cancelled`\|`completed`), `enrolled_at`, `progress_percentage`, `completed_at` | Not org-scoped — `UNIQUE(user_id, course_id)` pair, one row per student/course regardless of Org |

No `Enrollment` Eloquent model exists. Never create one. `course_user`
managed purely as pivot through `Course::students()`/`User::courses()`
(`app/Models/Course.php`, `app/Models/User.php`), exactly as
`courses-architecture` documents. So `EnrollmentController` authorizes
every action against parent `Course` via `CoursePolicy` (`update`
ability) — same "no policy of its own, authorize against parent" pattern
`ModulePolicy`/`LessonPolicy` use for `Module`/`Lesson`.

## Two Independent Revocation Mechanisms — Do Not Conflate Them

- **Link-level revocation** (`invitation_links.revoked_at`, set by
  `InvitationLinkController::destroy()`): stops *link itself* from being
  consumed again. Does **not** retroactively cancel enrollment already
  created through it. Revoked link is statement about URL, not about
  students who already joined.
- **Enrollment-level revocation** (`course_user.status = 'cancelled'`, set
  by `EnrollmentController::destroy()`, RF21): cancels one student's
  membership in one Course. No effect on any `InvitationLink` row.

Change to one must never write to other. Future requirement "revoking link
should also cancel everyone who joined through it" is new explicit
feature, not bug fix to either `destroy()` method.

## Public `/convite/{token}` Flow Is Deliberately Unauthenticated

`routes/web.php` `Route::middleware('guest')` group (not `auth`) covers
`GET convite/{token}` (`invitation.show`), `POST convite/check-email`
(`invitation.check-email`), `POST convite/{token}` (`invitation.store`).
All three run with no `Gate::authorize()` call, by design: unauthenticated
visitor is only actor these routes expect. Do not add `auth` middleware or
Policy to this controller. `guest` middleware itself keeps already-logged-in
user out of this flow — they get redirected away, same as hitting `/login`
while logged in.

## `InvitationLink::scopeUsable()` / `isUsable()` — One Source of Truth, Checked Twice

"May this link still be consumed?" is `! isExpired() && ! isExhausted() &&
! isRevoked() && courseIsAvailable()`, implemented once on model
(`InvitationLink::isUsable()`). `courseIsAvailable()` re-queries linked
`Course` (via `->course()->withoutGlobalScope('org')->value(
'is_published')`, bypassing only `OrgScope` — `SoftDeletingScope` stays on
purpose), so link pointing at soft-deleted or unpublished Course is as
unusable as expired/exhausted/revoked one; nothing to enroll invitee into
otherwise. `scopeUsable()` still covers only expired/exhausted/revoked trio
(predates this check, no controller calls it anymore — `isUsable()` is
single source of truth both `show()` and Action use now). Do not rely on
`scopeUsable()` alone to gate course availability. Evaluated deliberately
in **two different places** for two different reasons:

1. `InvitationController::show()` — `InvitationLink::query()
   ->withoutGlobalScopes()->usable()->where('token', $token)->first()` —
   pre-lock, read-only existence check, purely to render form (or throw
   `InvitationLinkInvalidException`, mapped to 404 in `bootstrap/app.php`).
2. `ProcessSmartInvitationAction::execute()` — re-checks `$invitationLink
   ->isUsable()` *after* acquiring `lockForUpdate()` inside transaction.
   Second check not redundant: without it, two concurrent requests against
   link at exactly `max_uses - 1` remaining uses both pass step 1 check
   before either increments `current_uses`, both insert enrollment. Only
   *lock* (not scope) serializes them, so second one's post-lock re-check
   correctly fails.

Both `show()` and Action call `->withoutGlobalScopes()` explicitly.
`InvitationLink` carries `OrgScope`, and these paths run with no
authenticated user (or, for Action, no *relevant* tenant context), so
ordinary scope "no user, no filter" branch already lets this through in
practice. Explicit call documents this must never silently start filtering
once someone touches `OrgScope` "no authenticated user" branch.

## RN09: Multi-Org Adaptive Enrollment, No Duplicate Accounts

`ProcessSmartInvitationAction` branches once, on whether `email` already
belongs to `User` row:

- **New e-mail**: creates `User` (role `aluno`), sets `org_id` to invitation
  link `org_id`. Only time this flow ever writes student `org_id`.
- **Existing e-mail**: verifies submitted password against existing row
  (`Hash::check`), reuses it as-is. **`org_id` never touched on this
  branch.** Student `org_id` reflects Org they first registered through,
  permanently, no matter how many other Orgs' courses they later join via
  other invite links. Multi-org tenancy for enrolled student lives entirely
  in their `course_user` rows (`user_id` × `course_id`), never in
  `users.org_id` — see `tenancy-architecture` note that `aluno.org_id` is
  "usually null" and does not scope their course access.

Either branch then upserts exactly one `course_user` row for
`[user_id, invitation_link.course_id]`: `firstOrCreate`-equivalent logic
(read-then-attach/reactivate, see `invitations-conventions`), not blind
`attach()`. Pair protected by real `UNIQUE(user_id, course_id)` constraint.
Student re-using invite link (or using second Org's link after being
`cancelled` from first enrollment) must never hit duplicate-key DB error.

## Staff Accounts Are Rejected By The Self-Service Flow

If e-mail submitted to `POST /convite/{token}` belongs to existing `User`
with `role:gestor` or `role:admin`, `ProcessSmartInvitationAction` throws
`ValidationException::withMessages(['email' => [...]])` — before checking
password — instead of silently enrolling staff account as student.
Deliberate decision, not oversight: staff member is not "aluno", and
falling through this flow would give Gestor/Admin `course_user` row (and
course-access UI) meant for students, of Course they may not administer.
Distinct rejection from wrong-password case (`errors.password`) — check
`errors.email` to diagnose which branch fired.

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `spec/specs/00-architecture-database-and-guardrails.md` §2.1 — full
  `invitation_links`/`course_user` column/index definitions.
- `courses-architecture` — `Course::students()`/`User::courses()`, pivot
  shape, why `Module`/`Lesson` authorize against parent instead of owning
  Policy (same pattern `EnrollmentController` follows for `course_user`).
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, why `aluno.org_id` is
  not source of truth for student course access.
