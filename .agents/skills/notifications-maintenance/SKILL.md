---
name: notifications-maintenance
description: >
  Debug, test, edge-case guide for Notifications & Alerts:
  mandatory PHPUnit/Dusk test files, duplicate-notification/recipient-set/
  mail-isolation failure modes, stale-`public/build` gotcha for
  NotificationBell.js, role:gestor/role:aluno bell-visibility regression. Use
  when NotificationTriggersTest, NotificationBellTest (Feature or Browser), or
  certificate/enrollment/forum-reply test fails; student gets duplicate
  notification e-mails; bell badge/dropdown not updating in browser; or before
  touching Notification/Event/Listener class and need to know what else must
  change with it.
license: MIT
metadata:
  feature: notifications
  role: maintenance
---

# Notifications Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/NotificationTriggersTest.php` — all 4 triggers via
  `Notification::fake()`/`Mail::fake()`, `database` row assertions,
  forum-reply recipient-set correctness (dedupe + self-exclusion),
  mail-failure-does-not-roll-back-the-transaction case, org isolation.
- `tests/Feature/NotificationBellTest.php` — `notifications.unread-count`
  accuracy, `notifications.read-all` marks all/only own rows,
  `notifications.read` + `action_url` passthrough, guest redirect,
  cross-user isolation (user A cannot mark/read user B notification even by
  guessing UUID).
- `tests/Browser/NotificationBellTest.php` — bell renders for gestor/aluno,
  never for Admin; badge reflects unread count; dropdown lists 10 most
  recent (`ORDER BY created_at DESC`); "marcar todas como lidas" clears
  badge; clicking item redirects to `action_url` and marks it read.

```bash
vendor/bin/sail artisan test --filter=NotificationTriggersTest
vendor/bin/sail artisan test --filter=NotificationBellTest
vendor/bin/sail artisan dusk --filter=NotificationBellTest
```

## Duplicate Notification E-mails on Every Course Access

Almost always means `IssueCertificateAction` `CertificateIssuedNotification`
dispatch moved off `wasRecentlyCreated` branch. Check exact call site fires
only when `Certificate::firstOrCreate(...)->wasRecentlyCreated === true`,
never on idempotent re-fetch (existing certificate, progress recalculated
again) or `QueryException`-race-recovery path. See
`certificates-maintenance` for full eligibility/idempotency picture this
dispatch sits inside of.

## Forum Reply Recipient Notified Twice (or Zero Times)

- Twice: `SendNewForumReplyNotifications` `merge()` lost its `->unique()`.
  Topic author, if also prior replier, must collapse to single id before
  `foreach`.
- Zero times for topic author: confirm `collect([$topic->user_id])` still
  seeds merge, not accidentally dropped.
- Replier who just posted still got notified: confirm
  `->reject(fn (int $userId): bool => $userId === $reply->user_id)`
  wasn't removed. Current poster must never be own recipient.
- `$reply->topic` resolves `null` inside Listener/queued job: means
  `ForumTopic::query()->withoutGlobalScopes()->findOrFail(...)` got replaced
  with scoped `belongsTo` relation. Multi-org Aluno reply (or any queued
  re-hydration outside original request tenant context) needs explicit
  bypass, same as `ForumReplyController::resolveTopic()` own convention. See
  `forum-maintenance` for analogous `OrgScope` gotcha on forum side.

## Enrollment Confirmed Fires on No-op Re-submit

Check both call sites, `EnrollmentController::store()` and
`ProcessSmartInvitationAction`. They dispatch `EnrollmentConfirmed` only on
actual transition (brand-new `course_user` row, or `cancelled → active`),
never when pivot row already `active` and unchanged. Regression here means
student who re-visits invite link they already used gets re-notified every
time.

## Mail Transport Failure Aborted Whole Request

Means `Send*Notification` Listener `try/catch (Throwable)` boundary around
`->notify()` call was removed, or narrowed to specific exception type not
covering actual transport exception thrown. `QUEUE_CONNECTION=sync` runs
notification job inline. Unhandled exception there bubbles all way up
through Listener to HTTP response, past point where triggering DB row
already committed, turning successful business action into 500. Restore
`try/catch (Throwable)` + `Log::error()` pattern exactly as documented in
`notifications-conventions`. Do not narrow caught type.

## `NotificationController::read()` Returns 404 for Row That Exists

Expected, not bug, **if** UUID belongs to another user — it must be
indistinguishable from genuinely nonexistent id. If happening for
*authenticated* user own row, check `{notification}` wasn't accidentally
typed as `DatabaseNotification $notification` implicit binding. No Policy
backs it, so binding either always succeeds regardless of ownership (an
isolation regression) or always fails depending on config. Neither correct. Must stay
plain `string` resolved through
`$request->user()->notifications()->findOrFail($notification)`.

## Bell Badge/Dropdown Not Updating in Browser

- Confirm `resources/js/modules/NotificationBell.js` registered in
  `resources/js/app.js` `DOMContentLoaded` bootstrap and `public/build` not
  stale relative to it. Run `vendor/bin/sail npm run build` (or ask user to
  never `npm run dev`/`composer run dev`, which leave `public/hot` behind
  and break every Dusk run — see `laravel-dusk`). Stale build is single most common
  cause of "bell renders but nothing happens on click". Blade markup ships
  from current source, bundled JS wiring it up does not.
- Badge stuck at stale count: check `data-unread-count-url` on
  `[data-notifications-bell]` still resolves to `notifications.unread-count`.
  Missing/renamed route name means `bind()` silently no-ops (`if
  (!this.unreadCountUrl) return;` inside `refreshUnreadCount()`).
- Dropdown never opens in Dusk test: same stale-build cause as above.
  `waitFor('@notifications-dropdown')` timing out after click on
  `@notifications-toggle` almost always means JS never bound click handler
  because bundle predates `NotificationBell.js`.
- Authenticated Admin sees bell: check `<x-notifications-bell>`
  `$canSeeNotifications` gate still checks
  `hasRole(RolesEnum::GESTOR->value) || hasRole(RolesEnum::ALUNO->value)`,
  not loosened to bare `@auth`.

## Auto-Update Protocol

Any change
to Notification class in `app/Notifications/`, Event/Listener pair in
`app/Events/`/`app/Listeners/` for one of 4 triggers,
`NotificationController`, `notifications.*` routes,
`resources/views/components/notifications-bell.blade.php`, or
`resources/js/modules/NotificationBell.js` **must** update all three
notifications skills (`notifications-architecture`,
`notifications-conventions`, `notifications-maintenance`) in same change,
before task counts done. `.agents/skills` is only real location for these
three skills in this repo. `.ai/skills` and `.goose/skills` do not mirror
this project's module skill set (they carry only generic
`laravel-specialist`/`laravel-verification` and `caveman-*` skills), so no
sync step needed there. Verify this hasn't changed before assuming
otherwise.

## Related

- `certificates-maintenance` — `wasRecentlyCreated`/idempotency gotcha
  trigger 2 dispatch site sits inside of.
- `forum-maintenance` — `withoutGlobalScopes()`/`OrgScope` gotcha trigger 3
  recipient resolution shares.
- `invitations-maintenance` — `InvitationLink` creation flow trigger 1 hooks
  into.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)**. One method drives create → edit → state change → delete →
consequence. **Not** by module or feature. Consequences when
maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, maybe in file named after another
  module when journey crosses module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file name.
  Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying its own UI **and** DB assertion. New method only
  for independent negatives (403, cross-tenant, other actor). New file only
  for genuinely new journey.
- **Debugging failure**: stack trace points at step, not whole scenario.
  Match line to its `// N.` comment. Late failure usually means earlier step
  did not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
