---
name: notifications-architecture
description: >
  Notifications & Alerts domain (SPEC-13). 4 triggers: invitation created,
  certificate issued, new forum reply, enrollment confirmed. Why
  `notifications` reuse Laravel stock `Notifiable`/`DatabaseNotification`
  shape, no bespoke model. Event, Listener, Notification pipeline per
  trigger. Mail failure never rolls back transaction. Admin never gets any
  of 4 types. Use when designing or reviewing feature touching
  `notifications` rows, before adding 5th trigger, or deciding how new
  business event notifies recipients.
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

SPEC-13/RF28 gives Gestor and Aluno topbar bell. 4 independent business
triggers (§2):

| # | Trigger | Channels | Recipient |
| --- | --- | --- | --- |
| 1 | `InvitationLink` created | `mail` only | link creator (a `User`, addressed by e-mail, not `->notify()`) |
| 2 | `Certificate` issued (genuine issuance only) | `database` + `mail` | student certificate belongs to |
| 3 | New `ForumReply` posted | `database` + `mail` | topic author + prior distinct repliers, minus whoever just posted |
| 4 | `course_user` created or transitions into `active` | `database` + `mail` | enrolled student |

Admin never gets any of 4. Topbar bell role-gated
(`role:gestor`/`role:aluno`, see `notifications-conventions`). No
Notification class in module ever dispatched to Admin.

## `notifications` Table Is Framework Shape, No New Migration

`database/migrations/2026_08_01_000022_create_notifications_table.php`
existed before SPEC-13. Laravel standard `Notification::createTable()`
shape: UUID `id`, `type`, morphs `notifiable_type`/`notifiable_id`, `data`
JSON, nullable `read_at`, timestamps. `App\Models\User` already `use`s
framework `Notifiable` trait. SPEC-13 adds **zero** schema. Every
notification class is plain `Illuminate\Notifications\Notification`. Every
read in module goes through `$user->notifications()`/
`$user->unreadNotifications()`, never bespoke `Notification` Eloquent model
or Policy.

## Event, Listener, Notification. Not Direct `->notify()` Call Site

3 of 4 triggers (invitation, forum reply, enrollment) go through dedicated
`Event`/auto-discovered `Listener` pair. No `->notify()` straight from
controller/action creating underlying row:

```
InvitationLinkController::store()  → InvitationLinkCreated  → SendInvitationSentNotification
ForumReplyController::store()      → ForumReplyPosted        → SendNewForumReplyNotifications
EnrollmentController::store() /
ProcessSmartInvitationAction        → EnrollmentConfirmed     → SendEnrollmentConfirmedNotification
```

4th trigger (certificate issued) is exception. Reuses **existing** SPEC-09
pipeline (`CourseCompletedByStudent`, `IssueCertificateOnCourseCompletion`,
`IssueCertificateAction`) instead of parallel event. Dispatches
`CertificateIssuedNotification` directly inside
`IssueCertificateAction::execute()`, right after `Certificate::firstOrCreate`
reports `wasRecentlyCreated === true`. Do not add
`EnrollmentCertificate...`-style event for this trigger. Existing
completion pipeline is single source of truth for "certificate genuinely
just issued". Duplicating it as second event risks second, inconsistent
eligibility check.

Every Listener auto-discovered: single type-hinted `handle()` parameter, no
`EventServiceProvider` registration. Matches every other Event/Listener pair
in codebase (`LessonMarkedAsCompleted` → `RecalculateCourseProgress`,
`CourseCompletedByStudent` → `IssueCertificateOnCourseCompletion`).

## Mail-Failure Isolation Boundary

RN (SPEC-13 §3): mail transport failure must never roll back business
transaction that just committed (invitation/reply/enrollment/certificate
row), nor abort HTTP response. `QUEUE_CONNECTION=sync` runs notification
`ShouldQueue` job inline, in-request. Boundary is
`try/catch (Throwable) { Log::error(...) }` wrapped **around
`->notify()`/`Notification::route(...)->notify()` call site itself**, inside
each Listener. Never around DB write that created triggering row. Never
left unwrapped hoping Laravel queue/notification internals swallow
exception; they don't, with `sync` driver. See every `Send*Notification`
Listener in `app/Listeners/` for exact pattern, and
`notifications-conventions` for snippet.

For 3 dual-channel triggers (certificate, forum reply, enrollment), `via()`
always lists `'database'` **before** `'mail'`. Laravel sends channels in
declared order, so if `mail` channel job throws, `database` row already
written, persists regardless. `InvitationSentNotification` is `mail`-only by
design (see below), so ordering guarantee does not apply there. Its own
try/catch is entire safety net.

## `InvitationSentNotification` Has No `database` Channel, No `User` Recipient

`invitation_links` is shareable link, not per-invitee row. No e-mail column
identifies who it is "for" until someone uses it. Trigger 1 therefore
notifies **link creator** (a `User` that does exist), but via
`Notification::route('mail', $email)` rather than `$creator->notify()`. Its
`via()` returns `['mail']` only. No `toDatabase()` method exists on class at
all. Do not add one "for consistency" with other 3. No bell-relevant
recipient event to record, and `database` row here would have no meaningful
`notifiable_id` to attach to.

## Recipient-Set Rules That Must Not Regress

- **Forum reply** (`SendNewForumReplyNotifications`): recipients are topic
  author **and** every distinct prior replier in same topic, **minus**
  whoever just posted this reply. If topic author is also prior replier,
  `collect([$topic->user_id])->merge(...)->unique()` guarantees exactly one
  notification, not two. Never `merge()` without `unique()->reject($replierId)`
  when touching this listener.
- **Certificate issued**: fires only on `Certificate::firstOrCreate()`'s
  `wasRecentlyCreated === true` branch inside `IssueCertificateAction`.
  Never on idempotent re-fetch (course re-accessed, progress recalculated
  again) or `QueryException`-race-recovery path. Regression here means
  student gets duplicate certificate e-mail on every course page load.
- **Enrollment confirmed**: fires on brand-new `course_user` row **or**
  `cancelled → active` transition, from both `EnrollmentController::store()`
  (Gestor-driven, RF21) and `ProcessSmartInvitationAction` (self-service
  invite, RF03). Never on already-`active`, unchanged enrollment. No
  double-notify on no-op re-submit.

## Related Specs

- `spec/specs/13-notifications-and-alerts.md` — RF28, 4-trigger table (§2),
  mail-isolation RN (§3).
- `certificates-architecture` — `IssueCertificateAction`
  eligibility/idempotency engine trigger 2 hooks into.
- `forum-architecture` — topic/reply schema trigger 3 recipient resolution
  walks, and why it deliberately excludes "new report" from trigger list.
- `invitations-architecture` — `InvitationLink` shape trigger 1 hooks into,
  and RN09 no-per-invitee-email rationale.
- `tenancy-architecture` — why `ForumTopic` `OrgScope` requires
  `withoutGlobalScopes()` when Listener/queued Notification resolves it
  outside original request tenant context.
