---
name: notifications-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Notifications &
  Alerts feature (SPEC-13): the try/catch-around-the-notify()-call-site
  mail-isolation pattern, the database-before-mail via() ordering
  convention, NotificationController's manual $request->user()->notifications()
  scoping (no Policy/OrgScope exists for DatabaseNotification), the
  role:gestor/role:aluno bell visibility gate, and the
  NotificationBell.js/HttpClient JS module contract. Use whenever writing
  a Notification class, Event/Listener pair, controller, or JS module
  that touches `notifications` rows or the topbar bell.
license: MIT
metadata:
  feature: notifications
  role: conventions
  specs:
    - spec/specs/13-notifications-and-alerts.md
---

# Notifications Conventions

## The try/catch Boundary Goes Around the `->notify()` Call Site, Not the DB Write

Every Listener in this module wraps only the notification dispatch in
`try/catch (Throwable)`, logging to `storage/logs/laravel.log` via
`Log::error()` and swallowing the exception — the triggering row (the
invitation/reply/enrollment/certificate) was already committed before the
Listener ever ran, so there is nothing left to roll back:

```php
public function handle(EnrollmentConfirmed $event): void
{
    try {
        $event->user->notify(new EnrollmentConfirmedNotification($event->course));
    } catch (Throwable $exception) {
        Log::error('Falha ao enviar notificação de matrícula confirmada.', [
            'course_id' => $event->course->id,
            'user_id' => $event->user->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

`SendNewForumReplyNotifications` wraps this **per recipient**, inside the
`foreach` loop — one recipient's mail failure must never prevent the
other recipients (or their own `database` row) from being notified. Copy
this per-recipient placement whenever a Listener fans out to more than
one notifiable.

`InvitationSentNotification`'s Listener uses the same pattern, just
targeting `Notification::route('mail', $email)` instead of a `User`
instance — see `notifications-architecture` for why there's no `User`
recipient for that trigger.

## `via()` Always Lists `'database'` Before `'mail'`

Every dual-channel Notification class (`CertificateIssuedNotification`,
`NewForumReplyNotification`, `EnrollmentConfirmedNotification`) returns:

```php
public function via(object $notifiable): array
{
    return ['database', 'mail'];
}
```

This ordering is load-bearing, not stylistic — Laravel sends channels in
declaration order, so the `database` row is guaranteed to have persisted
by the time the `mail` channel's job runs and potentially throws. Never
reorder this to `['mail', 'database']`.

## `toDatabase()`'s `data` Shape Is the Bell's Entire Contract

Every `toDatabase()` in this module returns at minimum:

```php
/**
 * @return array<string, mixed>
 */
public function toDatabase(object $notifiable): array
{
    return [
        'message' => '...',        // rendered as-is in the dropdown item
        'action_url' => route(...), // where clicking the item redirects
        // + one type-specific id key (course_id / certificate_id / reply_id)
    ];
}
```

`<x-notifications-bell>` and `NotificationController::read()` both read
`$notification->data['message']`/`$notification->data['action_url']`
directly — any new Notification class added to this module **must**
include both keys, or the dropdown falls back to `'Nova notificação'`/
`'#'` and the bell effectively breaks for that trigger.

## `NotificationController` Manually Scopes Every Query — No Policy Exists

`DatabaseNotification` carries no `OrgScope` and no Policy of its own (it
isn't even in this application's model tree — it's a framework model).
RN12 (no cross-user leak) is guaranteed entirely by resolving every
notification through the authenticated user's own relation, never a bare
`DatabaseNotification::find($id)`:

```php
public function read(Request $request, string $notification): JsonResponse
{
    $notificationModel = $request->user()->notifications()->findOrFail($notification);
    $notificationModel->markAsRead();

    return response()->json(['action_url' => $notificationModel->data['action_url'] ?? null]);
}
```

`{notification}` is a plain `string` route parameter, never a typed
`DatabaseNotification $notification` implicit binding — a route-model
binding would resolve the row regardless of ownership, silently
reintroducing the cross-user leak RN12 forbids. `findOrFail()` on the
scoped relation 404s identically whether the UUID belongs to another
user or doesn't exist at all — this is intentional, not an oversight
(indistinguishable on purpose, don't "fix" it into a 403).

## Bell Visibility: `role:gestor`/`role:aluno`, Not Just `@auth`

`<x-notifications-bell>` gates its entire render on:

```php
$canSeeNotifications = $notifUser
    && ($notifUser->hasRole(RolesEnum::GESTOR->value) || $notifUser->hasRole(RolesEnum::ALUNO->value));
```

An authenticated Admin (`@auth` alone would pass) must render **no**
bell — Admin is technically a `User` (sometimes `org_id === null`) but
never a recipient of any of the 4 SPEC-13 notification types. Never
relax this to a bare `@auth` check.

## Bell Dropdown Is Server-Rendered, No Separate "List" AJAX Endpoint

`<x-notifications-bell>` queries the 10 most recent rows and the unread
count **at render time** (`$user->notifications()->latest('created_at')->limit(10)->get()`,
`$user->unreadNotifications()->count()`) and embeds them directly in the
Blade markup. `NotificationBell.js` only polls `GET
notifications.unread-count` (30s) to keep the **badge** fresh — there is
no `GET notifications.list`/AJAX-refetch-the-dropdown endpoint in this
module's contract. Opening the dropdown (`toggleDropdown()`) also
triggers an immediate `refreshUnreadCount()` so the badge never visibly
lags the 30s tick, but the dropdown's item list itself only ever reflects
what was rendered server-side on the last full page load. Do not add a
dropdown-refetch endpoint without a deliberate spec change — it isn't
part of Bucket 2's contract.

## `NotificationBell.js` Follows `ForumPolling.js`'s Module Shape Exactly

Same constructor injection (`httpClient`, optional `intervalMs`), same
`init()` guard (`DOMContentLoaded` if `document.readyState === 'loading'`,
immediate `bind()` otherwise), same silent-catch-and-retry-next-tick on a
failed poll — no jQuery, no WebSockets (jQuery is not an installed
dependency; see CLAUDE.md's "don't add dependencies without approval"
and `ForumPolling.js`'s own docblock for the precedent). Registered once
in `resources/js/app.js`:

```js
window.NotificationBell = new NotificationBell(HttpClient);
// ...
window.NotificationBell.init();
```

Mark-single-read (`handleItemClick`) always fires the `PATCH
notifications.read` call **before** redirecting, but redirects via
`.finally(redirect)` regardless of whether that call succeeds — a
transient failure marking the row read must never trap the user on the
bell dropdown instead of taking them to `action_url`.

## Related Specs

- `spec/specs/13-notifications-and-alerts.md` — RF28, §2's 4-trigger
  table, §3's mail-isolation RN.
- `forum-conventions` — the `ForumPolling.js`/`HttpClient` JS module
  contract `NotificationBell.js` mirrors.
- `tenancy-security` — the general "never trust a route-bound id without
  manual ownership scoping" guardrail `NotificationController` follows
  for a model with no Policy of its own.
