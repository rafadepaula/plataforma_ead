---
name: notifications-maintenance
description: >
  Notifications & Alerts: 4 triggers (invitation created, certificate issued, forum
  reply, enrollment confirmed), stock `Notifiable`/`DatabaseNotification` shape,
  queued Notification classes building tenant links with `OrgUrl::route()`, try/catch
  mail isolation, database-before-mail via() ordering, bell visibility gate,
  `NotificationBell.js` contract. Use when designing or reviewing features touching
  `notifications` rows, before adding a 5th trigger, when writing a Notification
  class/Event/Listener/controller or JS module touching notifications or the topbar
  bell, or when `NotificationTriggersTest` or `NotificationBellTest` fails, a student
  gets duplicate notification e-mails, or the bell badge/dropdown stops updating.
license: MIT
metadata:
  feature: notifications
  roles: [architecture, conventions, maintenance]
---

# Notifications & Alerts (`notifications-maintenance`)

Notifications & Alerts: 4 triggers (invitation created, certificate issued, new forum reply, enrollment confirmed) over Laravel stock `Notifiable`/`DatabaseNotification`, queued Notification classes building tenant links via `OrgUrl::route()`, bell UI via `NotificationBell.js`.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching `notifications` rows, before adding a 5th trigger, or deciding how a new business event notifies recipients. |
| `resource/conventions.md` | Writing a Notification class, Event/Listener pair, controller, or JS module touching `notifications` rows or the topbar bell. |
| `resource/maintenance.md` | `NotificationTriggersTest` or `NotificationBellTest` (Feature or Browser) fails; student gets duplicate notification e-mails; bell badge/dropdown not updating in browser; before touching a Notification/Event/Listener class. |
