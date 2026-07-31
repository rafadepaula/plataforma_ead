# **11. Landing Page Pública e Central de Ajuda Contextual Multitenant**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF11:** Landing Page Pública (`/`).
* **RF12 / RN05:** Central de Ajuda cobrindo 100% das telas com `<x-help-button key="..." />` e suporte a artigos específicos por Organização (`org_id`) com fallback para o artigo global.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.

---

## **2. Componente Blade & Fallback de Ajuda**

- Resolução inteligente: Procura artigo com `target_page_key` para a `org_id` atual. Caso não exista, exibe o artigo padrão global.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `help_articles` com `org_id`
- [ ] Componente Blade `<x-help-button key="..." />`
- [ ] Harness: Criar/atualizar as 3 skills (`help-architecture`, `help-conventions`, `help-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `LandingPageTest.php`, `HelpCenterTest.php`, `ContextualHelpFallbackTest.php` aprovados com 100%.
