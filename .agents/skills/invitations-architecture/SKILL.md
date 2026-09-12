---
name: invitations-architecture
description: >
  Per-student unique Invitation domain: student_invitations schema,
  public unauthenticated /convite/{token} finalize flow, host-scoped
  redemption (wrong host reads as 404), identity bound to the token
  (e-mail/name pre-filled and immutable, never read from the request),
  pending→active credential lifecycle, typed InvitationInvalidException
  reason contract (one message per cause), manual enrollment panel reusing
  course_user/CoursePolicy instead of dedicated Enrollment model. Use when
  designing or reviewing feature touching StudentInvitation or course_user
  data, before adding new enrollment/invitation endpoint, or when deciding
  how the finalize-registration form behaves.
license: MIT
metadata:
  feature: invitations
  role: architecture
---

# Invitations Architecture

## Overview

The invitation is **unique per student**: the Gestor creates the Aluno
(one step: `users` row + `credentials` row + enrollment, via
`CreateOrgStudentAction` — an already-existing multi-org person is LINKED
into the Organization, never duplicated), enrolls them in
Courses through the normal panels, and then hands over ONE
`/convite/{token}` link that finalizes that person's own account — the
student opens it with e-mail/name **already filled and immutable**, sets
their own password and consents. Nobody (not even the Gestor) knows the
initial credential: it is born `pending` with a random password. The old
shareable per-Course link (a `max_uses` URL anyone could redeem) was
removed: a link that carries no identity cannot implement this flow.
Host-based tenancy governs everything here: a link is redeemable **only
on its own Organization's host**, and the account it finalizes is the
`credentials` row of the invitation's org — `users` is the global person
identity; per-org account data lives in `credentials` (see
`auth-orgs-architecture`).

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `student_invitations` | `org_id`, `token` (64-char, unique), `user_id`, `created_by` (nullable), `expires_at`, `used_at`, `revoked_at` | **Directly org-scoped** — `OrgScope` trait, same as `Course` |
| `course_user` (pivot) | `user_id`, `course_id`, `status` (`active`\|`cancelled`\|`completed`), `enrolled_at`, `progress_percentage`, `completed_at` | Not org-scoped — `UNIQUE(user_id, course_id)` pair, one row per student/course regardless of Org |

`student_invitations.user_id` is `ON DELETE CASCADE` (invitations die
with the student); `created_by` is `ON DELETE SET NULL` on purpose —
removing the Gestor who issued a link must never block a user deletion
(the old RESTRICT + `UserHasCreatedInvitationLinksException` guard died
with the shareable table).

No `Enrollment` Eloquent model exists. Never create one. `course_user`
is managed purely as pivot through `Course::students()`/`User::courses()`
(`app/Models/Course.php`, `app/Models/User.php`), exactly as
`courses-architecture` documents. So `EnrollmentController` authorizes
every action against parent `Course` via `CoursePolicy` (`update`
ability) — same "no policy of its own, authorize against parent" pattern
`ModulePolicy`/`LessonPolicy` use for `Module`/`Lesson`. The Gestor's
invitation endpoints (`gestor.students.invitations.*`) authorize via
`UserPolicy::updateStudent` — the same boundary as the student directory
itself (gestor of the same org + target holds the ALUNO role + an account
in the org).

## The Credential Lifecycle: `pending` ≠ `inactive`

`credentials.status` gained a third value, and the distinction is the
security core of this feature:

- **`pending`** — created by the Gestor (`EnrollmentController::storeStudent`
  creates it with `Hash::make(Str::random(32))`), awaiting the Aluno's
  unique-invite finalization. Redeeming the invitation ACTIVATES it
  (sets the chosen password + `status: 'active'`).
- **`active`** — usable account. Re-issuing an invitation for an active
  account and redeeming it replaces the password (the re-issued invite
  doubles as password reset, which is safe because issuance is
  Gestor-gated).
- **`inactive`** — Gestor-imposed deactivation. Redemption REJECTS it
  (`REASON_INACTIVE`, copy mirrors the login screen's "procure o gestor")
  and never resurrects it. A pending invitation must never become a
  deactivation override.

`OrgCredentialUserProvider` only accepts `active` for login, so a
`pending` account cannot log in by any path until finalized.

## Two Independent Revocation Mechanisms — Do Not Conflate Them

- **Invitation-level revocation** (`student_invitations.revoked_at`, set
  by `StudentInvitationController@regenerate`/`@destroy`): stops the
  *token itself* from being redeemed again. Regenerate = revoke live
  token + issue another, in one transaction (rotation when a link leaks);
  destroy = revoke without replacing. Neither touches enrollments.
- **Enrollment-level revocation** (`course_user.status = 'cancelled'`,
  set by `EnrollmentController::destroy()`): cancels one student's
  membership in one Course. No effect on any `StudentInvitation` row.

Change to one must never write to other. Redemption also does NOT create
or reactivate enrollments — enrollment is Gestor work done before the
link goes out; the token only finalizes the account.

## Public `/convite/{token}` Flow Is Deliberately Unauthenticated

`routes/web.php` `Route::middleware('guest')` group (not `auth`) covers
`GET convite/{token}` (`invitation.show`) and `POST convite/{token}`
(`invitation.store`). Both run with no `Gate::authorize()` call, by
design: unauthenticated visitor is the only actor these routes expect.
Do not add `auth` middleware or Policy to this controller. `guest`
middleware itself keeps already-logged-in user out of this flow — they
get redirected away, same as hitting `/login` while logged in. `POST`
is throttled per IP (`throttle:10,1`): it consumes a single-use
credential-setting token, so the rate limit keeps it from being probed
as a password-setting oracle. There is no `check-email` endpoint
anymore — the identity never comes from the request.

## The Token Is The Identity — The Request Cannot Redirect Redemption

`InvitationController::show()` renders `convite.show` with the
invitation's `student` relation: e-mail and name are rendered **readonly**
and the form's POST body carries only `password` +
`password_confirmation` + `consent` (`FinalizeStudentInvitationRequest`
validates nothing else; an extra `email` field in the payload is simply
discarded by `validated()`). `RedeemStudentInvitationAction` resolves
the account from `$invitation->user_id`, never from input — so a forged
field cannot point the password set at another person, and the
pre-registered e-mail is immutable by construction.

## Wrong-Host Redemption Reads As 404, Never As "Wrong Portal"

`InvitationController::resolveUsableInvitation()` (private, backs
`show()`) and `RedeemStudentInvitationAction::execute()` both compare
`(int) $invitation->org_id !== (int) OrgContext::current()->orgId()` and
throw `InvitationInvalidException::notFound($token)` on mismatch — the
same 404 (and same `userMessage()` copy) as a token that never existed.
A valid token hit from another Organization's portal must never reveal
that the link exists: no redirect to the right host, no distinct
message, no timing-visible branch. The Action re-checks **after**
`lockForUpdate()` so the verdict comes from the freshly locked row, not
from the state the caller read a moment earlier. Same token, same 404
copy, verified host-scoped in
`tests/Feature/Tenancy/PublicFlowsHostScopeTest.php`.

## `StudentInvitation::unusableReason()` / `isUsable()` — One Source of Truth, Checked Twice

"Why may this invitation no longer be redeemed?" is answered once, on
the model, by `StudentInvitation::unusableReason(): ?string` — a
`match (true)` in a **fixed precedence**: revoked > expired > used,
returning `null` when the invitation is still usable. `isUsable()` is
literally `unusableReason() === null`. The precedence is deliberate: one
row can sit in several unusable states at once (a revoked invitation
that also ran past `expires_at`), and the same row must always report
the same reason to the visitor, run after run. Evaluated deliberately in
**two different places** for two different reasons:

1. `InvitationController::show()` (via private
   `resolveUsableInvitation()`) — `StudentInvitation::query()
   ->withoutGlobalScopes()->where('token', $token)->first()`, then a
   **three-step verdict**: missing row → `::notFound($token)`; row whose
   `org_id` differs from the request host's org → `::notFound($token)`
   too (wrong-portal = never-existed); present-but-unusable row →
   `::forReason($invitation->unusableReason(), $token)`. Filtering the
   row out in SQL would collapse "expired" and "never existed" into the
   same 404 copy, so the lookup never chains `->usable()`.
2. `RedeemStudentInvitationAction::execute()` — repeats the org-host
   check and that same null-row/`unusableReason()` split *after*
   acquiring `lockForUpdate()` inside the transaction, so the verdict is
   resolved from the freshly locked row. Second check not redundant:
   without it, two concurrent requests against the same single-use token
   both pass the pre-lock check before either writes `used_at`; only the
   *lock* serializes them, so the second one's post-lock re-check
   correctly fails with `REASON_USED`.

Both paths call `->withoutGlobalScopes()` explicitly.
`StudentInvitation` carries `OrgScope`, and these paths run with no
authenticated user, so the ordinary "no user, no filter" branch already
lets this through in practice. Explicit call documents this must never
silently start filtering once someone touches `OrgScope`'s
"no authenticated user" branch.

## The Reason Contract: `InvitationInvalidException` Carries Copy, The View Never Does

`InvitationInvalidException` (`app/Exceptions/InvitationInvalidException.php`)
carries a typed reason, built through named constructors (`notFound()`,
`expired()`, `revoked()`, `used()`, plus `forReason(string $reason,
string $token)` for a verdict coming straight out of `unusableReason()`),
and exposes `reason()` and `userMessage()`:

| Reason constant | `userMessage()` (visitor-facing, verbatim) |
| --- | --- |
| `REASON_NOT_FOUND` | Este convite não foi encontrado. |
| `REASON_EXPIRED` | Este convite expirou. |
| `REASON_REVOKED` | Este convite foi cancelado. |
| `REASON_USED` | Este convite já foi utilizado. |
| `REASON_INACTIVE` | Esta conta está inativa. Procure o gestor da sua organização. |

`getMessage()` keeps the operational sentence (`Convite '{token}'
indisponível ({reason}).`) for the log; only `userMessage()` ever
reaches a screen. An unrecognised reason string degrades to
`REASON_NOT_FOUND` instead of throwing a second error inside the 404
handler.

Only `bootstrap/app.php`'s render hook turns that into a response — 404
in both channels, `response()->json(['message' => $e->userMessage()],
404)` for `expectsJson()` requests and `view('convite.invalid',
['message' => ...])` otherwise. `resources/views/convite/invalid.blade.php`
renders `$message` and keeps a single neutral fallback; it must never
grow a per-reason branch of its own — two copies of the same sentence
diverge and the test then asserts the wrong one.

## Staff Accounts Can Never Be Finalized Through A Token

If the pre-registered person has been promoted to `role:gestor`/
`role:admin` after the link went out, `RedeemStudentInvitationAction`
throws `InvitationInvalidException::notFound($token)` — the same 404 as
an unknown token, not a form error — instead of letting the token become
a staff password reset. Deliberate: the invitation surface is
Gestor-gated at issuance (`updateStudent` requires the ALUNO role), and
redemption re-verifies it.

## Gestor Surfaces

- `POST gestor/students/{user}/convite` (`issue`) — **get-or-create**: a
  usable invitation is returned if one exists, otherwise one is created.
  This is what makes the "Copiar convite" button idempotent. JSON
  `{ url }`, copied to the clipboard client-side (with an
  `execCommand('copy')` fallback for non-secure contexts).
- `POST gestor/students/{user}/convite/renovar` (`regenerate`) — revoke
  live + create new, in one transaction; redirects back with the new URL
  in the `invitation_url` flash banner.
- `DELETE gestor/students/{user}/convite` (`destroy`) — revoke without
  replacing.
- `EnrollmentController::storeStudent` issues the first invitation in
  the same transaction that creates the account/enrollment and flashes
  `invitation_url` back to the enrollments screen.

## Related

- `courses-architecture` — `Course::students()`/`User::courses()`, pivot
  shape, why `Module`/`Lesson` authorize against parent instead of owning
  Policy (same pattern `EnrollmentController` follows for `course_user`).
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, host resolution, why
  student tenancy lives in `credentials`/`course_user`, not on the
  `users` row.
- `auth-orgs-architecture` — `credentials` schema (including the
  `active|inactive|pending` enum), `Credential::forOrg`, the `(user, org)`
  login-identity pair this flow finalizes.
