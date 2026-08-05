---
name: notifications-maintenance
description: >
  Debugging, testing, and edge-case guide for the Notifications & Alerts
  feature (SPEC-13): the mandatory PHPUnit/Dusk test files, common
  duplicate-notification/recipient-set/mail-isolation failure modes, the
  stale-`public/build` gotcha for NotificationBell.js, and the
  role:gestor/role:aluno bell-visibility regression to watch for. Use when
  NotificationTriggersTest, NotificationBellTest (Feature or Browser), or
  a certificate/enrollment/forum-reply test is failing; a student gets
  duplicate notification e-mails; the bell badge/dropdown isn't updating
  in the browser; or you're about to touch a Notification/Event/Listener
  class and need to know what else must change with it.
license: MIT
metadata:
  feature: notifications
  role: maintenance
  specs:
    - spec/specs/13-notifications-and-alerts.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Notifications Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-13 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/NotificationTriggersTest.php` — all 4 triggers via
  `Notification::fake()`/`Mail::fake()`, `database` row assertions,
  forum-reply recipient-set correctness (dedupe + self-exclusion), the
  mail-failure-does-not-roll-back-the-transaction case, RN12 org
  isolation.
- `tests/Feature/NotificationBellTest.php` — `notifications.unread-count`
  accuracy, `notifications.read-all` marks all/only own rows,
  `notifications.read` + `action_url` passthrough, guest redirect,
  cross-user isolation (user A cannot mark/read user B's notification
  even by guessing the UUID).
- `tests/Browser/NotificationBellTest.php` — bell renders for gestor/aluno,
  never for Admin; badge reflects unread count; dropdown lists the 10
  most recent (`ORDER BY created_at DESC`); "marcar todas como lidas"
  clears the badge; clicking an item redirects to `action_url` and marks
  it read.

```bash
vendor/bin/sail artisan test --filter=NotificationTriggersTest
vendor/bin/sail artisan test --filter=NotificationBellTest
vendor/bin/sail artisan dusk --filter=NotificationBellTest
```

## Duplicate Notification E-mails on Every Course Access

Almost always means `IssueCertificateAction`'s `CertificateIssuedNotification`
dispatch got moved off the `wasRecentlyCreated` branch — check the exact
call site fires only when `Certificate::firstOrCreate(...)->wasRecentlyCreated
=== true`, never on the idempotent re-fetch (existing certificate,
progress recalculated again) or the `QueryException`-race-recovery path.
See `certificates-maintenance` for the full eligibility/idempotency
picture this dispatch sits inside of.

## A Forum Reply Recipient Got Notified Twice (or Zero Times)

- Twice: `SendNewForumReplyNotifications`'s `merge()` lost its
  `->unique()` — the topic author, if also a prior replier, must collapse
  to a single id before the `foreach`.
- Zero times for the topic author: confirm `collect([$topic->user_id])`
  is still the seed of the merge, not accidentally dropped.
- The replier who just posted still got notified: confirm
  `->reject(fn (int $userId): bool => $userId === $reply->user_id)`
  wasn't removed — the current poster must never be their own recipient.
- `$reply->topic` resolves `null` inside the Listener/queued job: this
  means `ForumTopic::query()->withoutGlobalScopes()->findOrFail(...)` got
  replaced with the scoped `belongsTo` relation — a multi-org Aluno's
  reply (or any queued re-hydration outside the original request's
  tenant context) needs the explicit bypass, same as
  `ForumReplyController::resolveTopic()`'s own convention. See
  `forum-maintenance` for the analogous `OrgScope` gotcha on the forum
  side.

## Enrollment Confirmed Fires on a No-op Re-submit

Check both call sites — `EnrollmentController::store()` and
`ProcessSmartInvitationAction` — dispatch `EnrollmentConfirmed` only on
an actual transition (brand-new `course_user` row, or `cancelled →
active`), never when the pivot row is already `active` and unchanged. A
regression here means a student who re-visits an invite link they
already used gets re-notified every time.

## A Mail Transport Failure Aborted the Whole Request

Means a `Send*Notification` Listener's `try/catch (Throwable)` boundary
around the `->notify()` call was removed, or narrowed to a specific
exception type that doesn't cover the actual transport exception thrown.
`QUEUE_CONNECTION=sync` runs the notification job inline — an unhandled
exception there bubbles all the way up through the Listener to the HTTP
response, past the point where the triggering DB row already committed,
turning a successful business action into a 500. Restore the
`try/catch (Throwable)` + `Log::error()` pattern exactly as documented in
`notifications-conventions` — do not narrow the caught type.

## `NotificationController::read()` Returns 404 for a Row That Exists

Expected, not a bug, **if** the UUID belongs to another user — RN12
requires this to be indistinguishable from a genuinely nonexistent id.
If it's happening for the *authenticated* user's own row, check
`{notification}` wasn't accidentally typed as a `DatabaseNotification
$notification` implicit binding (there is no Policy backing it, so a
binding would either always succeed regardless of ownership — a RN12
regression — or always fail depending on how it's configured; neither is
correct). It must stay a plain `string` resolved through
`$request->user()->notifications()->findOrFail($notification)`.

## Bell Badge/Dropdown Not Updating in the Browser

- Confirm `resources/js/modules/NotificationBell.js` is registered in
  `resources/js/app.js`'s `DOMContentLoaded` bootstrap and that
  `public/build` isn't stale relative to it — run `vendor/bin/sail npm
  run build` (or ask the user to run `npm run dev`/`composer run dev`).
  A stale build is the single most common cause of "the bell renders but
  nothing happens on click" — the Blade markup ships from the current
  source, but the bundled JS wiring it up doesn't.
- Badge stuck at a stale count: check `data-unread-count-url` on
  `[data-notifications-bell]` still resolves to `notifications.unread-count`
  — a missing/renamed route name means `bind()` silently no-ops (`if
  (!this.unreadCountUrl) return;` inside `refreshUnreadCount()`).
- Dropdown never opens in a Dusk test: same stale-build cause as above —
  `waitFor('@notifications-dropdown')` timing out after a click on
  `@notifications-toggle` almost always means the JS never bound the
  click handler because the bundle predates `NotificationBell.js`.
- An authenticated Admin sees the bell: check
  `<x-notifications-bell>`'s `$canSeeNotifications` gate still checks
  `hasRole(RolesEnum::GESTOR->value) || hasRole(RolesEnum::ALUNO->value)`
  and wasn't loosened to a bare `@auth`.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to a Notification class in `app/Notifications/`, an Event/Listener
pair in `app/Events/`/`app/Listeners/` for one of the 4 triggers,
`NotificationController`, the `notifications.*` routes,
`resources/views/components/notifications-bell.blade.php`, or
`resources/js/modules/NotificationBell.js` **must** update all three
notifications skills (`notifications-architecture`,
`notifications-conventions`, `notifications-maintenance`) in the same
change, before the task is considered done. `.agents/skills` is the only
real location for these three skills in this repository — `.ai/skills`
and `.goose/skills` do not mirror the spec-specific skill set (they only
carry the generic `laravel-specialist`/`laravel-verification` and
`caveman-*` skills respectively), so no sync step is needed there; verify
this hasn't changed before assuming otherwise.

## Related Specs

- `spec/specs/13-notifications-and-alerts.md` — RF28, §2's 4-trigger
  table, §3's mail-isolation RN.
- `certificates-maintenance` — the `wasRecentlyCreated`/idempotency
  gotcha trigger 2's dispatch site sits inside of.
- `forum-maintenance` — the `withoutGlobalScopes()`/`OrgScope` gotcha
  trigger 3's recipient resolution shares.
- `invitations-maintenance` — the `InvitationLink` creation flow trigger
  1 hooks into.
