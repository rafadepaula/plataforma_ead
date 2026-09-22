---
name: dashboard-maintenance
description: >
  Admin Dashboard, Analytics & System Settings: `admin.dashboard` route contract,
  `DashboardMetricsService` admin-global-vs-gestor branching, streamed O(1)-RAM CSV
  export, `system_settings` org-then-global override, Organizations summary table,
  `<x-ui.*>`-only Blade composition and `dusk` selector contracts. Use when designing
  or reviewing features touching dashboard KPIs, the CSV export pipeline or org-level
  SMTP/logo/signature settings, before adding a new stat/report type, when writing
  controller/service/Blade/test for Dashboard or the settings screen, or when
  `OrgDashboardTest`, `MultiTenantCsvExportTest` or `DashboardDuskTest` fails, a KPI
  shows a cross-org number, or the CSV export buffers instead of streaming.
license: MIT
metadata:
  feature: dashboard
  roles: [architecture, conventions, maintenance]
---

# Admin Dashboard, Analytics & System Settings (`dashboard-maintenance`)

Admin Dashboard, Analytics & System Settings: `admin.dashboard` route contract, `DashboardMetricsService` org-scoping for cascade-inherited models, streamed CSV export (O(1) RAM), `system_settings` org-then-global override resolution, and the Admin-global "Resumo das Organizações" summary table.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching dashboard KPIs, the CSV export pipeline, or org-level SMTP/logo/signature settings; before adding a new stat/report type. |
| `resource/conventions.md` | Writing controller, service, Blade view, or test touching Dashboard, CSV export, or the `system_settings` org-override screen. |
| `resource/maintenance.md` | `OrgDashboardTest`, `MultiTenantCsvExportTest`, or `DashboardDuskTest` fails; KPI shows a wrong (cross-org) number; CSV export silently buffers instead of streaming; Organizations summary table shows/hides for the wrong actor. |
