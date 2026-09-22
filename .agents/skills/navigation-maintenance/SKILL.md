---
name: navigation-maintenance
description: >
  Dynamic Navigation Menu & Access Control: `NavigationRegistry` ->
  `NavigationService` -> `NavigationComposer` pipeline, three-gate access filter
  (roles, permissions, route resolver) = menu/route-middleware parity, active-pattern
  sub-route highlight, org-scoped pending badges, `@sidebar-{key}-link` Dusk selector
  convention. Use when adding/changing a sidebar or topbar entry, wiring a role-gated
  screen into the menu, auditing link/permission parity, before touching
  `app/Services/Navigation/*` or layout Blade components, or when fixing a missing or
  leaked link, wrong active highlight or stale badge, or after renaming/adding a route
  the menu points at.
license: MIT
metadata:
  feature: navigation
  roles: [architecture, conventions, maintenance]
---

# Dynamic Navigation Menu & Access Control (`navigation-maintenance`)

Dynamic Navigation Menu: `NavigationRegistry` → `NavigationService` → `NavigationComposer` pipeline (no hardcoded Blade sidebar/topbar), three-gate access filter (roles, permissions, route resolver) keeping menu/route-middleware parity, active-pattern highlight, org-scoped badges.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Adding/changing a sidebar or topbar entry, wiring a role-gated screen into the menu, or auditing link/permission parity. |
| `resource/conventions.md` | Before touching `app/Services/Navigation/*`, layout Blade components, or their tests; adding/editing entries in `NavigationRegistry`, registering badges. |
| `resource/maintenance.md` | Fixing a navigation bug (missing/leaked link, wrong active highlight, stale badge count) or after renaming/adding a route the menu points at. |
