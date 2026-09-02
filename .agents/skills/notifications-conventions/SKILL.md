---
name: notifications-conventions
description: >
  Code patterns, snippets, guardrails for Notifications & Alerts:
  try/catch around notify() call site for mail isolation, database-before-mail
  via() ordering, NotificationController manual $request->user()->notifications()
  scoping (no Policy/OrgScope for DatabaseNotification), role:gestor/role:aluno
  bell visibility gate, NotificationBell.js/HttpClient JS module contract. Use
  when writing Notification class, Event/Listener pair, controller, or JS
  module touching `notifications` rows or topbar bell.
license: MIT
metadata:
  feature: notifications
  role: conventions
---

# Notifications Conventions

## try/catch Boundary Wraps `->notify()` Call Site, Not DB Write

Every Listener in module wraps only notification dispatch in
`try/catch (Throwable)`, logs to `storage/logs/laravel.log` via
`Log::error()`, swallows exception. Triggering row (invitation/reply/
enrollment/certificate) already committed before Listener ran, so nothing
left to roll back:

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

`SendNewForumReplyNotifications` wraps this **per recipient**, inside
`foreach` loop. One recipient mail failure must never block other recipients
(or their own `database` row). Copy this per-recipient placement whenever
Listener fans out to more than one notifiable.

`InvitationSentNotification` Listener uses same pattern, just targets
`Notification::route('mail', $email)` instead of `User` instance. See
`notifications-architecture` for why no `User` recipient exists for that
trigger.

## `via()` Always Lists `'database'` Before `'mail'`

Every dual-channel Notification class (`CertificateIssuedNotification`,
`NewForumReplyNotification`, `EnrollmentConfirmedNotification`) returns:

```php
public function via(object $notifiable): array
{
    return ['database', 'mail'];
}
```

Ordering is load-bearing, not stylistic. Laravel sends channels in
declaration order, so `database` row guaranteed persisted by time `mail`
channel job runs and maybe throws. Never reorder to `['mail', 'database']`.

## `toDatabase()` `data` Shape Is Bell's Entire Contract

Every `toDatabase()` in module returns at minimum:

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
directly. Any new Notification class in module **must** include both keys,
else dropdown falls back to `'Nova notificação'`/`'#'` and bell breaks for
that trigger.

## `NotificationController` Manually Scopes Every Query. No Policy Exists

`DatabaseNotification` carries no `OrgScope`, no Policy. Not even in this
application model tree; framework model. The no-cross-user-leak guarantee is
enforced entirely by resolving every notification through the authenticated
user's own relation, never bare `DatabaseNotification::find($id)`:

```php
public function read(Request $request, string $notification): JsonResponse
{
    $notificationModel = $request->user()->notifications()->findOrFail($notification);
    $notificationModel->markAsRead();

    return response()->json(['action_url' => $notificationModel->data['action_url'] ?? null]);
}
```

`{notification}` is plain `string` route parameter, never typed
`DatabaseNotification $notification` implicit binding. Route-model binding
resolves row regardless of ownership, silently reintroduces the forbidden
cross-user leak. `findOrFail()` on scoped relation 404s identically whether
UUID belongs to another user or does not exist at all. Intentional, not
oversight. Indistinguishable on purpose, don't "fix" it into 403.

## Bell Visibility: `role:gestor`/`role:aluno`, Not Just `@auth`

`<x-notifications-bell>` gates entire render on:

```php
$canSeeNotifications = $notifUser
    && ($notifUser->hasRole(RolesEnum::GESTOR->value) || $notifUser->hasRole(RolesEnum::ALUNO->value));
```

Authenticated Admin (`@auth` alone would pass) must render **no** bell.
Admin is technically a `User` (sometimes `org_id === null`) but never
recipient of any of this module's 4 notification types. Never relax to bare
`@auth` check.

## Bell Dropdown Server-Rendered, No Separate "List" AJAX Endpoint

`<x-notifications-bell>` queries 10 most recent rows and unread count **at
render time** (`$user->notifications()->latest('created_at')->limit(10)->get()`,
`$user->unreadNotifications()->count()`), embeds them directly in Blade
markup. `NotificationBell.js` only polls `GET notifications.unread-count`
(30s) to keep **badge** fresh. No `GET notifications.list`/AJAX-refetch-the-dropdown
endpoint in module contract. Opening dropdown (`toggleDropdown()`) also
triggers immediate `refreshUnreadCount()` so badge never visibly lags 30s
tick, but dropdown item list only ever reflects what was rendered
server-side on last full page load. Do not add a dropdown-refetch endpoint
without a deliberate design change; it is not part of this module's contract.

## `NotificationBell.js` Follows `ForumPolling.js` Module Shape Exactly

Same constructor injection (`httpClient`, optional `intervalMs`), same
`init()` guard (`DOMContentLoaded` if `document.readyState === 'loading'`,
immediate `bind()` otherwise), same silent-catch-and-retry-next-tick on
failed poll. No jQuery, no WebSockets. jQuery is not installed dependency;
see CLAUDE.md "don't add dependencies without approval" and
`ForumPolling.js` own docblock for precedent. Registered once in
`resources/js/app.js`:

```js
window.NotificationBell = new NotificationBell(HttpClient);
// ...
window.NotificationBell.init();
```

Mark-single-read (`handleItemClick`) always fires `PATCH notifications.read`
call **before** redirecting, but redirects via `.finally(redirect)`
regardless of whether call succeeds. Transient failure marking row read must
never trap user on bell dropdown instead of taking them to `action_url`.

## Related Skills

- `forum-conventions` — `ForumPolling.js`/`HttpClient` JS module contract
  `NotificationBell.js` mirrors.
- `tenancy-security` — general "never trust route-bound id without manual
  ownership scoping" guardrail `NotificationController` follows for model
  with no Policy of its own.
