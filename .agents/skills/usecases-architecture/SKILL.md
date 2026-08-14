---
name: usecases-architecture
description: >
  Use Cases documentation architecture of Plataforma EAD: three-pillar structure
  (full_spec.md, usecases/ individual files, usecases/index.md traceability
  matrix), UC naming convention (UCxx-slug.md), mandatory 7-section template,
  zero-orphan-requirement policy (every RF links to >=1 RN and >=1 UC; every RN
  links to >=1 RF and enforced by >=1 UC). Use when need to know how use-case
  documents organized, which files to touch when adding new UC, or how
  traceability between RF/RN/UC maintained.
license: MIT
metadata:
  feature: usecases
  role: architecture
  specs:
    - spec/docs/full_spec.md
    - spec/docs/usecases/index.md
---

# Use Cases Architecture

## Overview

Requirements and functional specification docs live under `spec/docs/`, organized
around three pillars:

```
spec/docs/
├── full_spec.md             # Master System Specification Document
├── multitenancy.md          # Supplementary multitenancy architecture doc
└── usecases/
    ├── index.md             # Master Index & Cross-Traceability Matrix
    ├── UC01-autenticacao-logout-e-recuperacao-de-senha.md
    ├── UC02-gestao-de-perfil-do-usuario.md
    └── ... (UC01 to UC23+)
```

## The Three Pillars

### 1. `spec/docs/full_spec.md` — Master Document

Consolidated technical spec for EAD Multitenant System. Contains:

- **Section 1**: Scope and vision
- **Section 2**: Technical architecture (PHP 8.5 / Laravel 13, MariaDB/MySQL)
- **Section 3**: Database schema (23 tables)
- **Section 4**: Functional Requirements matrix (**RF01–RF33+**)
- **Section 5**: Non-Functional Requirements (**RNF01–RNF07**)
- **Section 6**: Business Rules (**RN01–RN16+**)
- **Section 7**: Use Case summary and links
- **Section 8**: Traceability matrix

### 2. `spec/docs/usecases/` — Individual Use Case Files

Each file describes one Use Case in depth, derived by reverse-engineering actual
codebase (routes, controllers, actions, middleware, form requests, Blade views,
JS modules). Naming convention:

```
UCxx-kebab-case-name.md
```

`xx` is zero-padded sequential integer (e.g., `UC01`, `UC10`, `UC23`).

**Current UC inventory (UC01–UC23):**

| UC   | Name / Slug |
| :--- | :--- |
| UC01 | autenticacao-logout-e-recuperacao-de-senha |
| UC02 | gestao-de-perfil-do-usuario |
| UC03 | gestao-de-organizacoes-e-impersonate-org |
| UC04 | gestao-de-usuarios-e-matriculas-manuais |
| UC05 | importacao-em-lote-de-usuarios-via-csv |
| UC06 | auto-cadastro-e-convite-inteligente-adaptativo |
| UC07 | gestao-de-cursos-modulos-e-reordenacao |
| UC08 | gestao-de-licoes-multimidia-e-sanitizacao |
| UC09 | consumo-de-aulas-sala-de-aula-e-players |
| UC10 | registro-e-rastreamento-de-progresso |
| UC11 | gestao-de-questionarios-e-provas |
| UC12 | realizacao-de-questionarios-pelo-aluno |
| UC13 | configuracao-de-regras-e-emissao-de-certificado |
| UC14 | validacao-publica-e-revogacao-de-certificados |
| UC15 | forum-de-discussao-historico-e-moderacao |
| UC16 | landing-page-e-central-de-ajuda-integral |
| UC17 | dashboard-gerencial-e-exportacao-csv-streaming |
| UC18 | configuracoes-do-sistema-globais-e-por-org |
| UC19 | central-de-notificacoes-in-app-e-email |
| UC20 | logs-de-auditoria-monitoramento-e-expurgo |
| UC21 | suite-de-testes-environment-dusk-e-ci-cd |
| UC22 | povoamento-automatizado-do-banco-seeders |
| UC23 | menu-de-navegacao-dinamico-e-controle-de-acesso |

### 3. `spec/docs/usecases/index.md` — Master Index & Traceability Matrix

Single source of truth for cross-referencing:

- **Catalog of Use Cases by Module** (relative links to each `UCxx` file)
- **Complete Cross-Traceability Matrix** (RF vs RN vs UC)
- **Business Rule Coverage Matrix** (RN vs RF vs UC)

## The Zero-Orphan-Requirement Policy

> **ZERO TOLERANCE FOR ORPHAN REQUIREMENTS:**
>
> 1. Every Functional Requirement (**RF**) MUST link to **at least one Business
>    Rule (RN)** and **at least one Use Case (UC)**.
> 2. Every Business Rule (**RN**) MUST link to **at least one RF** and be
>    enforced by **at least one UC**.
> 3. No Use Case created without explicitly declaring which RFs and RNs it
>    satisfies.

## Mandatory 7-Section UC Template

Every file in `spec/docs/usecases/` MUST follow this structure:

1. **Identification** — ID, name, module, actors, version
2. **Linked RFs & RNs** — table of every RF and RN the UC satisfies
3. **Overview & Goal** — concise description of UC's functional purpose
4. **System Conditions** — Pre-conditions and Post-conditions
5. **Execution Flows** — Happy path + alternative paths (step-by-step, referencing
   exact route, controller, service, view, JS module names)
6. **Exception Flows** — Each error case: trigger, HTTP status, exact UI message
7. **Technical Signature** — Routes, middleware, controllers/services, JS & Blade
   component names

## Module to UC Mapping

Each UC belongs to one platform module:

| Module | UCs |
| :--- | :--- |
| Authentication & Profile | UC01, UC02 |
| Organizations & Tenancy | UC03 |
| User Management | UC04, UC05 |
| Invitations & Enrollment | UC06 |
| Courses & Content | UC07, UC08 |
| Student Learning | UC09, UC10 |
| Quizzes | UC11, UC12 |
| Certificates | UC13, UC14 |
| Forum | UC15 |
| Help & Landing Page | UC16 |
| Dashboard & Settings | UC17, UC18 |
| Notifications | UC19 |
| Audit Logs | UC20 |
| Testing Infrastructure | UC21 |
| Seeders | UC22 |
| Navigation | UC23 |
