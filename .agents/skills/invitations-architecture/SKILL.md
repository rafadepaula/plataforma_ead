---
name: invitations-architecture
description: >
  Smart Invitation & Enrollment domain: invitation_links schema,
  public unauthenticated /convite/{token} flow, multi-org
  no-duplicate-account guarantee, typed
  InvitationLinkInvalidException reason contract (one message per cause), manual enrollment panel reusing
  course_user/CoursePolicy instead of dedicated Enrollment model. Use when
  designing or reviewing feature touching InvitationLink or course_user
  data, before adding new enrollment/invitation endpoint, or when deciding
  how multi-org adaptive registration form behaves.
license: MIT
metadata:
  feature: invitations
  role: architecture
---

# Invitations Architecture

## Overview

Every Course has a shareable `/convite/{token}` link. Unauthenticated
visitor self-registers (or authenticates into existing account) and gets
enrolled in that one Course, one step, no admin. Gestor
(`role:gestor`)/Admin get a manual enroll-or-revoke panel over same
`course_user` rows, for cases with no invite link. The
no-duplicate-account guarantee binds both:
student who already has account (maybe tied to different Organization)
must never get second `users` row just because they used different Org's
invite link.

## Schema

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
  by `EnrollmentController::destroy()`): cancels one student's
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

## `InvitationLink::unusableReason()` / `isUsable()` — One Source of Truth, Checked Twice

"Why may this link no longer be consumed?" is answered once, on the model,
by `InvitationLink::unusableReason(): ?string` — a `match (true)` in a
**fixed precedence**: revoked > expired > exhausted > Course unavailable,
returning `null` when the link is still usable. `isUsable()` is now literally
`unusableReason() === null`, so the boolean and the reason can never drift
apart. The precedence is deliberate: one row can sit in several unusable
states at once (a revoked link that also ran past `expires_at`), and the same
row must always report the same reason to the visitor, run after run. `courseIsAvailable()` re-queries linked
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
   ->withoutGlobalScopes()->where('token', $token)->first()`, then a
   **two-step verdict**: a missing row throws
   `InvitationLinkInvalidException::notFound($token)`, a present-but-unusable
   row throws `::forReason($invitationLink->unusableReason(), $token)`. The
   lookup deliberately no longer chains `->usable()`: filtering the row out in
   SQL would collapse "expired" and "never existed" into the same 404 copy.
2. `ProcessSmartInvitationAction::execute()` — repeats that same
   null-row/`unusableReason()` split *after* acquiring `lockForUpdate()`
   inside the transaction, so the reason is resolved from the freshly locked
   row (a link exhausted by a concurrent request reports `REASON_EXHAUSTED`,
   not whatever state the caller read a moment earlier).
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

## The Reason Contract: `InvitationLinkInvalidException` Carries Copy, The View Never Does

`InvitationLinkInvalidException` (`app/Exceptions/InvitationLinkInvalidException.php`)
is no longer a bare `RuntimeException` with an ad-hoc string. It carries a typed
reason, built through named constructors (`notFound()`, `expired()`, `revoked()`,
`exhausted()`, `courseUnavailable()`, plus `forReason(string $reason, string $token)`
for a verdict coming straight out of `unusableReason()`), and exposes
`reason()` and `userMessage()`:

| Reason constant | `userMessage()` (visitor-facing, verbatim) |
| --- | --- |
| `REASON_NOT_FOUND` | Este convite não foi encontrado. |
| `REASON_EXPIRED` | Este convite expirou. |
| `REASON_REVOKED` | Este convite foi cancelado. |
| `REASON_EXHAUSTED` | Limite de vagas atingido. |
| `REASON_COURSE_UNAVAILABLE` | Este convite não está mais disponível. |

`getMessage()` keeps the operational sentence (`Convite '{token}' indisponível
({reason}).`) for the log; only `userMessage()` ever reaches a screen. An
unrecognised reason string degrades to `REASON_NOT_FOUND` instead of throwing a
second error inside the 404 handler. The public constructor stays
`RuntimeException`-compatible (`__construct(string $message = '', string $reason
= REASON_NOT_FOUND, ...)`), so `expectException(...)` assertions written before
this change still hold.

Only `bootstrap/app.php`'s render hook turns that into a response — 404 in both
channels, `response()->json(['message' => $e->userMessage()], 404)` for
`expectsJson()` requests and `view('convite.invalid', ['message' => ...])`
otherwise. `resources/views/convite/invalid.blade.php` renders `$message` and keeps a
single neutral fallback (`Este convite não está mais disponível.`) for a render
with no `$message` bound; it must never grow a per-reason branch of its own —
two copies of the same sentence diverge and the test then asserts the wrong one.

## Multi-Org Adaptive Enrollment, No Duplicate Accounts

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

## Related

- `courses-architecture` — `Course::students()`/`User::courses()`, pivot
  shape, why `Module`/`Lesson` authorize against parent instead of owning
  Policy (same pattern `EnrollmentController` follows for `course_user`).
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, why `aluno.org_id` is
  not source of truth for student course access.
