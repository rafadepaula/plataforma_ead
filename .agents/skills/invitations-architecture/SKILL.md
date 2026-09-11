---
name: invitations-architecture
description: >
  Smart Invitation & Enrollment domain: invitation_links schema,
  public unauthenticated /convite/{token} flow, host-scoped redemption
  (wrong host reads as 404), adaptive form keyed on the host org's
  `credentials` account, typed
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
`course_user` rows, for cases with no invite link. Host-based tenancy
governs everything here: a link is redeemable **only on its own
Organization's host**, and the account consumed or created is the
`credentials` row of the host org — a person already enrolled in another
portal simply gets a new account here (`credentials` row for this org),
never a second `users` row (`users` is the global person identity;
per-org account data lives in `credentials` — see `auth-orgs-architecture`).

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
while logged in. Both public POSTs are throttled per IP (`throttle:20,1` on
`invitation.check-email`, `throttle:10,1` on `invitation.store`,
`routes/web.php:300-305`): they answer questions about personal data to
unauthenticated callers, so the rate limit keeps them from being usable as
enumeration oracles.

## Wrong-Host Redemption Reads As 404, Never As "Wrong Portal"

`InvitationController::resolveUsableLink()` (private, backs `show()`) and
`ProcessSmartInvitationAction::execute()` both compare
`(int) $invitationLink->org_id !== (int) OrgContext::current()->orgId()`
and throw `InvitationLinkInvalidException::notFound($token)` on mismatch —
the same 404 (and same `userMessage()` copy) as a token that never
existed. A valid token hit from another Organization's portal must never
reveal that the link exists: no redirect to the right host, no distinct
message, no timing-visible branch. The Action re-checks **after**
`lockForUpdate()` so the verdict comes from the freshly locked row, not
from the state the caller read a moment earlier. Same token, same 404
copy, verified host-scoped in
`tests/Feature/Tenancy/PublicFlowsHostScopeTest.php`.

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

1. `InvitationController::show()` (via private `resolveUsableLink()`) —
   `InvitationLink::query()
   ->withoutGlobalScopes()->where('token', $token)->first()`, then a
   **three-step verdict**: a missing row throws
   `InvitationLinkInvalidException::notFound($token)`, a row whose
   `org_id` differs from the request host's org throws `::notFound($token)`
   too (wrong-portal = never-existed), and a present-but-unusable row
   throws `::forReason($invitationLink->unusableReason(), $token)`. The
   lookup deliberately no longer chains `->usable()`: filtering the row out in
   SQL would collapse "expired" and "never existed" into the same 404 copy.
2. `ProcessSmartInvitationAction::execute()` — repeats the org-host check
   and that same null-row/`unusableReason()` split *after* acquiring
   `lockForUpdate()` inside the transaction, so the verdict is resolved
   from the freshly locked row (a link exhausted by a concurrent request
   reports `REASON_EXHAUSTED`, not whatever state the caller read a moment
   earlier).
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

## Adaptive Enrollment Operates On The Host Org's `credentials` Account

`ProcessSmartInvitationAction` branches on the `(user, host-org)` pair,
not on the e-mail alone. After the link guards pass, the Action looks up
`User::query()->where('email', $data['email'])->first()`, then:

- **Person exists, holds credential in the link's org**
  (`Credential::forOrg($invitationLink->org_id)` hit): submitted password
  is verified against **that credential's** hash (`Hash::check` against
  `$credential->password`, not any global column — `users.password` no
  longer exists). Wrong password surfaces as `errors.password`. An
  `inactive` credential is blocked **after** the password check
  (`errors.email`, "Esta conta está inativa...") so status is never
  disclosed to someone who cannot authenticate into it — same order the
  login flow uses.
- **Person exists, no credential in this org** (account lives in another
  portal only): the Action **creates** this portal's account —
  `Credential::create([...'org_id' => $invitationLink->org_id, 'password'
  => Hash::make($data['password']), 'status' => 'active'])` — with the
  password chosen on this form. The other portal's credential keeps its
  own password, untouched.
- **New person**: creates `User` (role `aluno`, `email_verified_at` set)
  **and** the org's `Credential`. `users` receives no `org_id` — the
  column no longer exists; the link's org lands on the `credentials` row.

Multi-org tenancy for the enrolled student lives in two places, never in
a `users.org_id` (gone with the host-tenancy migration): the per-org
accounts in `credentials`, and the `course_user` rows (`user_id` ×
`course_id`). See `tenancy-architecture` and `auth-orgs-architecture`.
The adaptive form mirrors this exactly: `/convite/check-email` answers
`exists` **by credential existence in the host org**
(`Credential::query()->forOrg($context->orgId())->where('user_id',
$user->id)->exists()`), so a person known only to another portal sees the
full form here and types a brand-new password for this portal.

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
- `tenancy-architecture` — `OrgScope`, `RolesEnum`, host resolution, why
  student tenancy lives in `credentials`/`course_user`, not on the
  `users` row.
- `auth-orgs-architecture` — `credentials` schema, `Credential::forOrg`,
  the `(user, org)` login-identity pair this flow creates accounts
  against.
