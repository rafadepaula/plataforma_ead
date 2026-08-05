---
name: invitations-architecture
description: >
  Explains the Smart Invitation & Enrollment domain (SPEC-06): the
  invitation_links schema, the public unauthenticated /convite/{token}
  flow, RN09's multi-org no-duplicate-account guarantee, and how RF21's
  manual enrollment panel reuses course_user/CoursePolicy rather than a
  dedicated Enrollment model. Use whenever designing or reviewing a feature
  that touches InvitationLink or course_user data, before adding a new
  enrollment/invitation endpoint, or when deciding how the multi-org
  adaptive registration form should behave.
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

RF03 gives every Course a shareable `/convite/{token}` link that lets an
unauthenticated visitor self-register (or authenticate into an existing
account) and be enrolled in that one Course, in one step, with no admin
intervention. RF21 gives a Gestor (`role:gestor`)/Admin a manual
enroll-or-revoke panel over the same `course_user` rows for cases where no
invite link is used. RN09 is the constraint threading both together: a
student who already has an account (possibly tied to a different
Organization) must never end up with a second `users` row just because
they used a different Org's invite link.

## Schema (SPEC-00 §2.1, SPEC-06 §2)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `invitation_links` | `org_id`, `token` (64-char, unique), `course_id`, `max_uses`, `current_uses`, `expires_at`, `revoked_at`, `created_by` | **Directly org-scoped** — `OrgScope` trait, same as `Course` |
| `course_user` (pivot) | `user_id`, `course_id`, `status` (`active`\|`cancelled`\|`completed`), `enrolled_at`, `progress_percentage`, `completed_at` | Not org-scoped — a `UNIQUE(user_id, course_id)` pair, one row per student/course regardless of Org |

No `Enrollment` Eloquent model exists or should be created — `course_user`
is managed purely as a pivot through `Course::students()`/`User::courses()`
(`app/Models/Course.php`, `app/Models/User.php`), exactly as
`courses-architecture` already documents. `EnrollmentController` therefore
authorizes every action against the parent `Course` via `CoursePolicy`
(`update` ability), the same "no policy of its own, authorize against the
parent" pattern `ModulePolicy`/`LessonPolicy` use for `Module`/`Lesson`.

## Two Independent Revocation Mechanisms — Do Not Conflate Them

- **Link-level revocation** (`invitation_links.revoked_at`, set by
  `InvitationLinkController::destroy()`): stops the *link itself* from
  being consumable again. It does **not** retroactively cancel any
  enrollment already created through it — a link being revoked is a
  statement about the URL, not about the students who already joined
  through it.
- **Enrollment-level revocation** (`course_user.status = 'cancelled'`, set
  by `EnrollmentController::destroy()`, RF21): cancels one student's
  membership in one Course. It has no effect on any `InvitationLink` row at
  all.

A change to one must never write to the other. If a future requirement
asks "revoking a link should also cancel everyone who joined through it,"
that is a new, explicit feature — not a bug fix to either existing
`destroy()` method.

## The Public `/convite/{token}` Flow Is Deliberately Unauthenticated

`routes/web.php`'s `Route::middleware('guest')` group (not `auth`) covers
`GET convite/{token}` (`invitation.show`), `POST convite/check-email`
(`invitation.check-email`), and `POST convite/{token}` (`invitation.store`)
— all three run with no `Gate::authorize()` call, by design: an
unauthenticated visitor is the only actor these routes ever expect. Do not
add an `auth` middleware or a Policy to this controller; the `guest`
middleware itself is what keeps an already-logged-in user from re-entering
this flow (they get redirected away, same as hitting `/login` while
logged in).

## `InvitationLink::scopeUsable()` / `isUsable()` — One Source of Truth, Checked Twice

"May this link still be consumed?" is `! isExpired() && ! isExhausted() &&
! isRevoked() && courseIsAvailable()`, implemented once on the model
(`InvitationLink::isUsable()`). `courseIsAvailable()` re-queries the
linked `Course` (via `->course()->withoutGlobalScope('org')->value(
'is_published')`, bypassing only `OrgScope` — `SoftDeletingScope` is left
in place on purpose) so a link pointing at a soft-deleted or unpublished
Course is just as unusable as an expired/exhausted/revoked one; there is
nothing to enroll the invitee into otherwise. `scopeUsable()` still only
covers the expired/exhausted/revoked trio (it predates this check and is
no longer called by any controller — `isUsable()` is the single source of
truth both `show()` and the Action rely on now); do not rely on
`scopeUsable()` alone to gate course availability. It is deliberately
evaluated in
**two different places** for two different reasons:

1. `InvitationController::show()` — `InvitationLink::query()
   ->withoutGlobalScopes()->usable()->where('token', $token)->first()` — a
   pre-lock, read-only existence check purely to render the form (or throw
   `InvitationLinkInvalidException`, mapped to a 404 in `bootstrap/app.php`).
2. `ProcessSmartInvitationAction::execute()` — re-checks `$invitationLink
   ->isUsable()` *after* acquiring `lockForUpdate()` inside the
   transaction. This second check is not redundant: without it, two
   concurrent requests against a link at exactly `max_uses - 1` remaining
   uses could both pass step 1's check before either has incremented
   `current_uses`, both then insert an enrollment, and only the *lock*
   (not the scope) serializes them so the second one's post-lock re-check
   correctly fails.

Both `show()` and the Action call `->withoutGlobalScopes()` explicitly —
`InvitationLink` carries `OrgScope`, and these code paths run with no
authenticated user (or, for the Action, no *relevant* tenant context), so
the ordinary scope's "no user → no filter" branch already lets this
through in practice, but the explicit call documents that this must never
silently start filtering once someone touches `OrgScope`'s "no
authenticated user" branch.

## RN09: Multi-Org Adaptive Enrollment, No Duplicate Accounts

`ProcessSmartInvitationAction` branches once, on whether `email` already
belongs to a `User` row:

- **New e-mail** → creates the `User` (role `aluno`), setting `org_id` to
  the invitation link's `org_id`. This is the *only* time a student's
  `org_id` is ever written by this flow.
- **Existing e-mail** → verifies the submitted password against the
  existing row (`Hash::check`) and reuses it as-is. **`org_id` is never
  touched on this branch** — a student's `org_id` reflects the Org they
  first registered through, permanently, regardless of how many other
  Orgs' courses they later join via other invite links. Multi-org tenancy
  for an enrolled student lives entirely in their `course_user` rows
  (`user_id` × `course_id`), never in `users.org_id` — see
  `tenancy-architecture`'s note that `aluno.org_id` is "usually null" and
  is not what scopes their course access.

Either branch then upserts exactly one `course_user` row for
`[user_id, invitation_link.course_id]`: `firstOrCreate`-equivalent logic
(read-then-attach/reactivate, see `invitations-conventions`) rather than a
blind `attach()`, because the pair is protected by a real `UNIQUE
(user_id, course_id)` constraint — a student re-using an invite link (or
using a second Org's link after being `cancelled` from a first enrollment)
must never hit a duplicate-key database error.

## Staff Accounts Are Rejected By The Self-Service Flow

If the e-mail submitted to `POST /convite/{token}` belongs to an existing
`User` who holds `role:gestor` or `role:admin`, `ProcessSmartInvitationAction`
throws `ValidationException::withMessages(['email' => [...]])` — before
ever checking the password — rather than silently enrolling a staff
account as a student. This is a deliberate decision, not an oversight: a
staff member is not an "aluno", and letting them fall through this flow
would let a Gestor/Admin end up with a `course_user` row (and course-access
UI) meant for students, of a Course they may not even administer. It is a
distinct rejection from the wrong-password case (`errors.password`) —
check `errors.email` when diagnosing which branch fired.

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `spec/specs/00-architecture-database-and-guardrails.md` §2.1 — full
  `invitation_links`/`course_user` column/index definitions.
- `courses-architecture` — `Course::students()`/`User::courses()`, the
  pivot's shape, and why `Module`/`Lesson` authorize against their parent
  rather than owning a Policy (the same pattern `EnrollmentController`
  follows for `course_user`).
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, and why `aluno.org_id`
  is not the source of truth for a student's course access.
