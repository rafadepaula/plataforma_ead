---
name: spec-grill-me
description: Use when initiating a new feature specification or clarifying underspecified requirements in spec/specs/ through an interactive grill-me session.
---

# Spec Grill-Me (`spec-grill-me`)

## Overview

`spec-grill-me` orchestrates a systematic `/grill-me` interview session to extract 100% of business rules (RN), functional requirements (RF), database schema impact, security controls, roles, and edge cases for a feature. It formats the resulting specification into a new `.md` file in `spec/specs/` adhering strictly to the project's multitenant spec pattern, naming conventions, and testing guardrails. Additionally, it invokes the **`usecases-maintenance`** skill to generate all associated Use Case documents in `spec/docs/usecases/`, sync `spec/docs/usecases/index.md`, and update the master specification in `spec/docs/full_spec.md`.

---

## When to Use

Use `spec-grill-me` whenever:
- A new feature or module needs to be specified before development starts.
- An existing specification in `spec/specs/` is incomplete or needs deep refinement.
- You need to conduct an interactive `/grill-me` session to clarify requirements, business rules, and technical architecture with the user.

---

## The 5-Phase Spec Generation Pipeline

```
[Phase 1: Index & Context Discovery] ──► [Phase 2: Grill-Me Interactive Interview]
                                                            │
[Phase 5: Use Cases & Full Spec Sync] ◄── [Phase 4: README Sync] ◄── [Phase 3: Spec Markdown File Creation]
```

---

### Phase 1: Index & Context Discovery

1. **Inspect `spec/specs/`**:
   - Scan `spec/specs/` to list all existing spec files.
   - Identify the highest numerical prefix `XX` (e.g., `17-dynamic-navigation-menu-and-access-control.md` → highest index is `17`).
   - Assign the next sequential index `NEXT_INDEX = XX + 1` formatted with leading zero (e.g., `18`).
   - Determine the target filename: `spec/specs/{NEXT_INDEX}-{kebab-feature-name}.md`.

2. **Project Architecture Guardrails (Baseline Context)**:
   - **Stack**: PHP 8.5, Laravel 13, Blade + Bootstrap 5.3 + Vanilla JS/jQuery Clean Code/SOLID, SQLite test DB.
   - **Multitenancy**: Single-database multitenant architecture with `org_id` column, `OrgScope` global scope on Eloquent models, `session('active_org_id')` for Impersonate Org.
   - **Roles**: Spatie Permission (`role:admin`, `role:gestor`, `role:aluno`).
   - **Quality Gate**: 95%+ code coverage mandate (`scripts/check-coverage.php`), PHPUnit test classes (`Tests\TestCase`), Dusk E2E browser tests.
   - **Harness Triad**: Every spec must define 3 harness skills: `{feature}-architecture`, `{feature}-conventions`, `{feature}-maintenance`.

---

### Phase 2: Grill-Me Interactive Interview

Conduct an interactive question-by-question interview using `ask_question`. **Do not ask all questions at once.** Ask one question (or logical group) at a time, providing a recommended default for each choice based on project conventions.

**Branches of the Design Tree to Cover**:

1. **Feature Overview & Scope**:
   - What is the primary purpose and core functional requirements (`RFxx`) of the feature?
   - Which user roles (`role:admin`, `role:gestor`, `role:aluno`) interact with this feature, and what actions can each role perform?
2. **Business Rules (`RNxx`) & Edge Cases**:
   - What are the strict business rules, limits, state transitions, validation constraints, and edge case behaviors?
   - What features or flows are explicitly **out of scope** for this feature?
3. **Multitenancy & Security**:
   - How is tenant isolation enforced (`org_id`, `OrgScope`, middleware like `EnsureStudentIsEnrolled`)?
   - Are there public/unauthenticated routes (e.g. verification, landing) or cross-tenant exceptions?
   - What security controls apply (XSS sanitization, rate-limiting, authorization policies)?
4. **Database Schema & Domain Architecture**:
   - What new Eloquent models, tables, columns, FK relationships, indexes, and soft-deletes are required?
   - What domain services, single-responsibility Actions, single-use jobs, or notifications are needed?
5. **UI/UX & Asset Requirements**:
   - Which Blade components, JS modules, AJAX polling/endpoints, or asset compilation changes are required?
6. **Testing & Quality Gate**:
   - What specific PHPUnit feature test classes and Dusk E2E browser test classes must be created?

---

### Phase 3: Spec Markdown File Creation

Write the completed specification to `spec/specs/{NEXT_INDEX}-{kebab-feature-name}.md` following the exact project writing pattern:

```markdown
# **{NEXT_INDEX}. {Feature Title} com Isolamento Multitenant**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **RF{XX}:** [Requirement description]
* **RN{YY}:** [Business rule description]
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.
* **Casos de Uso:** [UC references if applicable]

---

## **2. Modelo do Banco de Dados & Segurança**

- **`table_name`**: `id`, `org_id` (FK -> `organizations.id`), `column_1`, `column_2`, `created_at`, `updated_at`.
- **Isolamento & Segurança**:
  - `OrgScope` no Model `ModelName` e Middleware/Policies aplicáveis.
  - Controls for XSS, rate limiting, and role authorization.

---

## **3. Domain Services & Regras de Negócio**

- **`ServiceName` / `ActionName`**: [Description of single-responsibility actions, services, and transactions]
- **Tratamento de Exceções & Fila**: [Queue handling, failure policies, async/sync rules]

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `table_name` com a coluna `org_id`
- [ ] Model `ModelName` utilizando a Trait `OrgScope`
- [ ] Controller/Actions `ControllerName`
- [ ] Componentes Blade / Views / Assets JS
- [ ] Harness: Criar/atualizar as 3 skills (`{feature}-architecture`, `{feature}-conventions`, `{feature}-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `TestNameTest.php`, `DuskTestNameTest.php` aprovados com 100%.
```

---

### Phase 4: Master Index (`README.md`) Sync

Update `spec/specs/README.md`:
1. Add the new node and connections to the Mermaid TDD dependency graph in Section 1.
2. Add the new entry to the spec repository list in Section 2 with a relative clickable file link.

---

### Phase 5: Use Cases & Full Spec Synchronization (`usecases-maintenance`)

Invoking **`usecases-maintenance`** (see `usecases-maintenance` SKILL.md) is mandatory whenever a spec is created or updated:

1. **Generate Detailed Use Cases (`spec/docs/usecases/UCxx-*.md`)**:
   - For every functional requirement (`RFxx`) and business rule (`RNyy`) introduced in the spec, create or update individual Use Case markdown files following the 7-section standard.
   - Enforce the cardinal rule: Every `RF` MUST be linked to at least one `RN`, and every `RN` MUST be linked to `RF` and `UC`.
   - Document step-by-step user interactions, routes, Form Requests, validations, DOM/AJAX updates, and exception flows.

2. **Update Master Use Cases Index (`spec/docs/usecases/index.md`)**:
   - Add new UCs to the module catalogue with clickable Markdown links (`file:///...`).
   - Update the **Matriz Completa de Rastreabilidade Cruzada** (RF vs RN vs UC).
   - Update the **Matriz de Cobertura de Regras de Negócio** (RN vs RF vs UC).

3. **Update Master System Specification (`spec/docs/full_spec.md`)**:
   - **Section 2 (DB Schema)**: Update table list / schemas if new tables or columns were introduced.
   - **Section 3 (Matriz de Requisitos Funcionais - RF)**: Append new RFs with their linked RNs and UCs.
   - **Section 5 (Regras de Negócio - RN)**: Append new RNs with their linked RFs and UCs.
   - **Section 6 (Mapeamento de Casos de Uso)**: Append new UCs with relative file links.
   - **Section 7 (Matriz de Rastreabilidade)**: Synchronize the triple traceability matrix.

---

## Checklist for Completing a Spec

- [ ] Next sequential index `XX` correctly identified from existing files in `spec/specs/`.
- [ ] Interactive `/grill-me` session completed using `ask_question` with recommended options.
- [ ] All 100% of RF, RN, Roles, DB schema, Services, Security, and Edge Cases documented in `spec/specs/XX-kebab-name.md`.
- [ ] Harness skill triad (`{feature}-architecture`, `{feature}-conventions`, `{feature}-maintenance`) included in Section 4.
- [ ] `spec/specs/README.md` updated with Mermaid graph node and spec list entry.
- [ ] **`usecases-maintenance` executed**: Use Case files `spec/docs/usecases/UCxx-*.md` created/updated for all new requirements.
- [ ] `spec/docs/usecases/index.md` updated with new UCs and traceability matrices.
- [ ] `spec/docs/full_spec.md` updated with new RFs, RNs, UCs, DB Schema, and traceability matrix.
