# **12. Dashboard Gerencial, Analytics e Configurações Multitenant**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF18:** Dashboard Gerencial e Central de Exportação de Relatórios CSV em Streaming ($O(1)$ RAM).
* **Visão do Admin Global (`role:admin`):** KPIs globais e recurso de **Impersonate Org** para visualizar dashboards de Orgs específicas.
* **Visão do Gestor da Org (`role:gestor`):** KPIs e exportações restritos à sua `org_id`.
* **`system_settings`**: Permite overrides de SMTP, logos e assinaturas por Organização.

---

## **2. Central de Exportação CSV em Streaming por Org**

- Exportação em streaming HTTP (`StreamedResponse` + `fputcsv`) garantindo footprint de memória $O(1)$ em hospedagem compartilhada.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `system_settings` com suporte a `org_id`
- [ ] `SettingService` com cache e fallback
- [ ] Harness: Criar/atualizar as 3 skills (`dashboard-architecture`, `dashboard-conventions`, `dashboard-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `OrgDashboardTest.php`, `MultiTenantCsvExportTest.php` aprovados com 100%.
