---
name: notifications-architecture
description: >
  Explains the Notifications & Alerts domain (SPEC-13): the 4 triggers
  (invitation created, certificate issued, new forum reply, enrollment
  confirmed), why `notifications` reuses Laravel's stock
  `Notifiable`/`DatabaseNotification` shape instead of a bespoke model, the
  Event→Listener→Notification pipeline each trigger follows, the
  mail-failure-must-never-roll-back-the-transaction isolation boundary, and
  why Admin never receives any of the 4 notification types. Use whenever
  designing or reviewing a feature that touches `notifications` rows,
  before adding a 5th trigger, or when deciding how a new business event
  should notify its recipients.
license: MIT
metadata:
  feature: notifications
  role: architecture
  specs:
    - spec/specs/13-notifications-and-alerts.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Notifications Architecture

## Overview

SPEC-13/RF28 gives Gestor and Aluno a topbar bell fed by 4 independent
business triggers (§2):

| # | Trigger | Channels | Recipient |
| --- | --- | --- | --- |
| 1 | `InvitationLink` created | `mail` only | the link's creator (a `User`, addressed by e-mail, not `->notify()`) |
| 2 | `Certificate` issued (genuine issuance only) | `database` + `mail` | the student the certificate belongs to |
| 3 | New `ForumReply` posted | `database` + `mail` | topic author + prior distinct repliers, minus whoever just posted |
| 4 | `course_user` created or transitions into `active` | `database` + `mail` | the enrolled student |

Admin never receives any of the 4 — the topbar bell itself is role-gated
(`role:gestor`/`role:aluno`, see `notifications-conventions`), and no
Notification class in this module is ever dispatched to an Admin.

## `notifications` Table Is the Framework's Own Shape, No New Migration

`database/migrations/2026_08_01_000022_create_notifications_table.php`
already existed before SPEC-13 — it is Laravel's standard
`Notification::createTable()` shape (UUID `id`, `type`, morphs
`notifiable_type`/`notifiable_id`, `data` JSON, nullable `read_at`,
timestamps), and `App\Models\User` already `use`s the framework's
`Notifiable` trait. SPEC-13 adds **zero** schema — every notification
class is a plain `Illuminate\Notifications\Notification`, and every read
in this module goes through `$user->notifications()`/
`$user->unreadNotifications()`, never a bespoke `Notification` Eloquent
model or Policy.

## Event → Listener → Notification, Not a Direct `->notify()` Call Site

3 of the 4 triggers (invitation, forum reply, enrollment) go through a
dedicated `Event`/auto-discovered `Listener` pair rather than calling
`->notify()` directly from the controller/action that creates the
underlying row:

```
InvitationLinkController::store()  → InvitationLinkCreated  → SendInvitationSentNotification
ForumReplyController::store()      → ForumReplyPosted        → SendNewForumReplyNotifications
EnrollmentController::store() /
ProcessSmartInvitationAction        → EnrollmentConfirmed     → SendEnrollmentConfirmedNotification
```

The 4th trigger (certificate issued) is the one exception — it reuses the
**existing** SPEC-09 pipeline
(`CourseCompletedByStudent` → `IssueCertificateOnCourseCompletion` →
`IssueCertificateAction`) instead of adding a parallel event, dispatching
`CertificateIssuedNotification` directly from inside
`IssueCertificateAction::execute()` right after `Certificate::firstOrCreate`
reports `wasRecentlyCreated === true`. Do not add an `EnrollmentCertificate...`-style
event for this trigger — the existing completion pipeline is the single
source of truth for "a certificate was genuinely just issued", and
duplicating it as a second event risks a second, inconsistent
eligibility check.

Every Listener is auto-discovered (a single type-hinted `handle()`
parameter, no `EventServiceProvider` registration), matching every other
Event/Listener pair already in this codebase (`LessonMarkedAsCompleted` →
`RecalculateCourseProgress`, `CourseCompletedByStudent` →
`IssueCertificateOnCourseCompletion`).

## The Mail-Failure Isolation Boundary

RN (SPEC-13 §3): a mail transport failure must never roll back the
business transaction that just committed (the invitation/reply/
enrollment/certificate row), nor abort the HTTP response, since
`QUEUE_CONNECTION=sync` runs the notification's `ShouldQueue` job inline,
in-request. The boundary is a `try/catch (Throwable) { Log::error(...) }`
wrapped **around the `->notify()`/`Notification::route(...)->notify()`
call site itself**, inside each Listener — never around the DB write
that created the triggering row, and never left unwrapped hoping
Laravel's queue/notification internals swallow the exception for you
(they don't, with the `sync` driver). See every `Send*Notification`
Listener in `app/Listeners/` for the exact pattern, and
`notifications-conventions` for the snippet.

For the 3 dual-channel triggers (certificate, forum reply, enrollment),
`via()` always lists `'database'` **before** `'mail'` — Laravel sends
channels in that declared order, so if the `mail` channel's job throws,
the `database` row has already been written and persists regardless.
`InvitationSentNotification` is `mail`-only by design (see below), so
this ordering guarantee doesn't apply there — its own try/catch is the
entire safety net.

## `InvitationSentNotification` Has No `database` Channel, No `User` Recipient

`invitation_links` is a shareable link, not a per-invitee row — there is
no e-mail column identifying who it's "for" until someone actually uses
it. Trigger 1 therefore notifies the **link's creator** (a `User` who
does exist), but does so via `Notification::route('mail', $email)`
rather than `$creator->notify()`, and its `via()` returns `['mail']`
only — no `toDatabase()` method exists on this class at all. Do not add
one "for consistency" with the other 3 — there is no bell-relevant
recipient event to record, and a `database` row here would have no
`notifiable_id` to attach to that means anything.

## Recipient-Set Rules That Must Not Regress

- **Forum reply** (`SendNewForumReplyNotifications`): recipients are the
  topic author **and** every distinct prior replier in the same topic,
  **minus** whoever just posted this reply. If the topic author is also a
  prior replier, `collect([$topic->user_id])->merge(...)->unique()`
  guarantees exactly one notification, not two — never `merge()` without
  `unique()->reject($replierId)` when touching this listener.
- **Certificate issued**: fires only on `Certificate::firstOrCreate()`'s
  `wasRecentlyCreated === true` branch inside `IssueCertificateAction` —
  never on the idempotent re-fetch (course re-accessed, progress
  recalculated again) or the `QueryException`-race-recovery path. A
  regression here means a student gets a duplicate certificate e-mail on
  every course page load.
- **Enrollment confirmed**: fires on a brand-new `course_user` row
  **or** a `cancelled → active` transition, from both
  `EnrollmentController::store()` (Gestor-driven, RF21) and
  `ProcessSmartInvitationAction` (self-service invite, RF03) — never on
  an already-`active`, unchanged enrollment (no double-notify on a
  no-op re-submit).

## Related Specs

- `spec/specs/13-notifications-and-alerts.md` — RF28, the 4-trigger table
  (§2), the mail-isolation RN (§3).
- `certificates-architecture` — the `IssueCertificateAction`
  eligibility/idempotency engine trigger 2 hooks into.
- `forum-architecture` — the topic/reply schema trigger 3's recipient
  resolution walks (and why it deliberately excludes "new report" from
  the trigger list).
- `invitations-architecture` — the `InvitationLink` shape trigger 1
  hooks into, and RN09's no-per-invitee-email rationale.
- `tenancy-architecture` — why `ForumTopic`'s `OrgScope` requires
  `withoutGlobalScopes()` when a Listener/queued Notification resolves it
  outside the original request's tenant context.
