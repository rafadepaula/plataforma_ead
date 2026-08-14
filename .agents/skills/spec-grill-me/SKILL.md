---
name: spec-grill-me
description: Use when starting new feature specification or clarifying underspecified requirements in spec/specs/ through interactive grill-me session.
---

# Spec Grill-Me (`spec-grill-me`)

## Overview

`spec-grill-me` runs systematic `/grill-me` interview session to extract 100% of business rules (RN), functional requirements (RF), DB schema impact, security controls, roles, edge cases for feature. Formats resulting spec into new `.md` file in `spec/specs/`, strictly following project multitenant spec pattern, naming conventions, testing guardrails. Also invokes **`usecases-maintenance`** skill to generate all associated Use Case docs in `spec/docs/usecases/`, sync `spec/docs/usecases/index.md`, update master spec in `spec/docs/full_spec.md`.

---

## When to Use

Use `spec-grill-me` when:
- New feature or module needs spec before development starts.
- Existing spec in `spec/specs/` incomplete or needs deep refinement.
- You must run interactive `/grill-me` session to clarify requirements, business rules, technical architecture with user.

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
   - Scan `spec/specs/`, list all existing spec files.
   - Identify highest numeric prefix `XX` (e.g., `17-dynamic-navigation-menu-and-access-control.md` → highest index is `17`).
   - Assign next sequential index `NEXT_INDEX = XX + 1`, formatted with leading zero (e.g., `18`).
   - Determine target filename: `spec/specs/{NEXT_INDEX}-{kebab-feature-name}.md`.

2. **Project Architecture Guardrails (Baseline Context)**:
   - **Stack**: PHP 8.5, Laravel 13, Blade + Bootstrap 5.3 + Vanilla JS/jQuery Clean Code/SOLID, SQLite test DB.
   - **Multitenancy**: Single-database multitenant architecture with `org_id` column, `OrgScope` global scope on Eloquent models, `session('active_org_id')` for Impersonate Org.
   - **Roles**: Spatie Permission (`role:admin`, `role:gestor`, `role:aluno`).
   - **Quality Gate**: 95%+ coverage mandate (`scripts/check-coverage.php`), PHPUnit test classes (`Tests\TestCase`), Dusk E2E browser tests.
   - **Harness Triad**: Every spec must define 3 harness skills: `{feature}-architecture`, `{feature}-conventions`, `{feature}-maintenance`.

---

### Phase 2: Grill-Me Interactive Interview

Run interactive question-by-question interview with `ask_question`. **Do not ask all questions at once.** Ask one question (or logical group) at a time. Give recommended default for each choice, based on project conventions.

**Branches of the Design Tree to Cover**:

1. **Feature Overview & Scope**:
   - Primary purpose and core functional requirements (`RFxx`) of feature?
   - Which user roles (`role:admin`, `role:gestor`, `role:aluno`) touch this feature, and what actions can each role perform?
2. **Business Rules (`RNxx`) & Edge Cases**:
   - Strict business rules, limits, state transitions, validation constraints, edge case behaviors?
   - Which features or flows explicitly **out of scope**?
3. **Multitenancy & Security**:
   - How is tenant isolation enforced (`org_id`, `OrgScope`, middleware like `EnsureStudentIsEnrolled`)?
   - Any public/unauthenticated routes (e.g. verification, landing) or cross-tenant exceptions?
   - Which security controls apply (XSS sanitization, rate-limiting, authorization policies)?
4. **Database Schema & Domain Architecture**:
   - Which new Eloquent models, tables, columns, FK relationships, indexes, soft-deletes needed?
   - Which domain services, single-responsibility Actions, single-use jobs, notifications needed?
5. **UI/UX & Asset Requirements**:
   - Which Blade components, JS modules, AJAX polling/endpoints, asset compilation changes needed?
6. **Testing & Quality Gate**:
   - Which PHPUnit feature test classes and Dusk E2E browser test classes must be created?

---

### Phase 3: Spec Markdown File Creation

Write completed spec to `spec/specs/{NEXT_INDEX}-{kebab-feature-name}.md`, following exact project writing pattern:

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
1. Add new node and connections to Mermaid TDD dependency graph in Section 1.
2. Add new entry to spec repository list in Section 2, with relative clickable file link.

---

### Phase 5: Use Cases & Full Spec Synchronization (`usecases-maintenance`)

Invoking **`usecases-maintenance`** (see `usecases-maintenance` SKILL.md) is mandatory whenever spec created or updated:

1. **Generate Detailed Use Cases (`spec/docs/usecases/UCxx-*.md`)**:
   - For every functional requirement (`RFxx`) and business rule (`RNyy`) introduced in spec, create or update individual Use Case markdown files, following 7-section standard.
   - Enforce cardinal rule: every `RF` MUST link to at least one `RN`, and every `RN` MUST link to `RF` and `UC`.
   - Document step-by-step user interactions, routes, Form Requests, validations, DOM/AJAX updates, exception flows.

2. **Update Master Use Cases Index (`spec/docs/usecases/index.md`)**:
   - Add new UCs to module catalogue with clickable Markdown links (`file:///...`).
   - Update **Matriz Completa de Rastreabilidade Cruzada** (RF vs RN vs UC).
   - Update **Matriz de Cobertura de Regras de Negócio** (RN vs RF vs UC).

3. **Update Master System Specification (`spec/docs/full_spec.md`)**:
   - **Section 2 (DB Schema)**: Update table list / schemas if new tables or columns introduced.
   - **Section 3 (Matriz de Requisitos Funcionais - RF)**: Append new RFs with linked RNs and UCs.
   - **Section 5 (Regras de Negócio - RN)**: Append new RNs with linked RFs and UCs.
   - **Section 6 (Mapeamento de Casos de Uso)**: Append new UCs with relative file links.
   - **Section 7 (Matriz de Rastreabilidade)**: Sync triple traceability matrix.

---

## Checklist for Completing a Spec

- [ ] Next sequential index `XX` correctly identified from existing files in `spec/specs/`.
- [ ] Interactive `/grill-me` session completed using `ask_question` with recommended options.
- [ ] All 100% of RF, RN, Roles, DB schema, Services, Security, Edge Cases documented in `spec/specs/XX-kebab-name.md`.
- [ ] Harness skill triad (`{feature}-architecture`, `{feature}-conventions`, `{feature}-maintenance`) included in Section 4.
- [ ] `spec/specs/README.md` updated with Mermaid graph node and spec list entry.
- [ ] **`usecases-maintenance` executed**: Use Case files `spec/docs/usecases/UCxx-*.md` created/updated for all new requirements.
- [ ] `spec/docs/usecases/index.md` updated with new UCs and traceability matrices.
- [ ] `spec/docs/full_spec.md` updated with new RFs, RNs, UCs, DB Schema, traceability matrix.
