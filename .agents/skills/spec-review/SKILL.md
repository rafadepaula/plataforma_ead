---
name: spec-review
description: Use when verifying codebase changes in a feature branch or PR against a target specification file in spec/specs/ to audit functional, non-functional, business rules, and test compliance and generate a report in spec/reviews/.
---

# Spec Review (`spec-review`)

## Overview

`spec-review` is a comprehensive specification audit skill for features developed in the codebase. It parses a target specification document (`.md`) from `spec/specs/`, analyzes the code changes in the active feature branch/PR, verifies 100% adherence to Functional Requirements (RF), Business Rules (RN), Non-Functional Requirements (RNF), Tenant Isolation, Security Protocols, and Test Coverage, and generates an actionable report file formatted as `spec_xx_review_report.md` inside `spec/reviews/`.

**Isolated Multi-Subagent Pipeline**: To prevent context bloat and ensure maximum precision, `spec-review` dispatches specialized subagents via `invoke_subagent` at each step. Each subagent operates within its own context window, performing deep code/spec analysis and reporting back structured results to the orchestrator.

---

## When to Use

Use `spec-review` whenever you need to audit a completed or in-progress feature branch against its specification before merging or finalizing code.

**Invocation Arguments**:
- `specFile`: (Required) Path or filename of the target specification file in `spec/specs/` (e.g., `spec/specs/05-courses-modules-and-content-management.md` or `05`).
- `baseBranch`: (Optional) Base branch for git diff comparison (default: `main` or `origin/main`).

---

## Multi-Subagent Verification Pipeline

```
                     ┌──────────────────────────────────────┐
                     │         Main Orchestrator            │
                     └──────────────────┬───────────────────┘
                                        │
           ┌────────────────────────────┼────────────────────────────┐
           │                            │                            │
           ▼                            ▼                            ▼
┌──────────────────────┐     ┌──────────────────────┐     ┌──────────────────────┐
│ Step 1 Subagent      │     │ Step 2 Subagent      │     │ Step 3 Subagents     │
│ Spec Deconstructor   │     │ Code Diff Scanner    │     │ Matrix Auditors      │
│ (Extracts RF/RN/RNF) │     │ (Maps Touched Files) │     │ (Parallel RF & RNF)  │
└──────────────────────┘     └──────────────────────┘     └──────────┬───────────┘
                                                                     │
                                        ┌────────────────────────────┘
                                        │
                                        ▼                            ▼
                             ┌──────────────────────┐     ┌──────────────────────┐
                             │ Step 4 Subagent      │     │ Step 5 Subagent      │
                             │ Test Suite Verifier  │     │ Report Synthesizer   │
                             │ (Runs PHPUnit/Dusk)  │     │ (Generates Report)   │
                             └──────────────────────┘     └──────────────────────┘
```

---

## Step-by-Step Multi-Subagent Execution Instructions

### Step 1: Spec Deconstruction (`Subagent: Spec Deconstructor`)

Dispatch a subagent via `invoke_subagent` to parse the target specification file and extract all verifiable requirements without polluting the main thread.

- **Role**: `Spec Deconstructor`
- **Subagent Type**: `research` or `self`
- **Task Prompt**:
  > Parse `spec/specs/[SPEC_FILE]`. Extract all Functional Requirements (RFxx), Business Rules (RNxx), Non-Functional Requirements (RNFxx / Tenant isolation, Security OWASP [REDACTED], XSS sanitization, upload paths), Database Schemas, UI/Blade components, and Acceptance Criteria. Return a clean, structured JSON requirement matrix.

- **Requirements Extracted**:
  1. **Requisitos Funcionais (`RFxx`)**: All endpoints, actions, and features.
  2. **Regras de Negócio (`RNxx`)**: State transitions, validation constraints, calculation logic, role gates.
  3. **Requisitos Não-Funcionais & Guardrails (`RNFxx`)**:
     - **Tenant Isolation**: `OrgScope` trait, `org_id` foreign keys, isolated storage paths (`storage/app/public/orgs/{org_id}/...`).
     - **RBAC**: `role:admin`, `role:gestor`, `role:aluno`, Policies (`authorize()`).
     - **Security**: Password masking (`[REDACTED]`), XSS `strip_tags`, URL sanitization (`YoutubeSanitizerService`).
     - **Auditoria**: `AuditLog`, `AuditableTrait`, `AuditObserver`, `AuditService::log()`.
  4. **Database Schemas & Models**: Tables, columns, foreign keys, indexes.
  5. **UI & Frontend Components**: Blade components `<x-ui...>`, JS modules, dynamic AJAX endpoints, `dusk="..."` test selectors.
  6. **Acceptance Criteria & Test Checklist**: Required feature, unit, and Dusk E2E tests.

---

### Step 2: Codebase & Git Diff Inspection (`Subagent: Code Diff Scanner`)

Dispatch a subagent to analyze all code changes made in the feature branch.

- **Role**: `Code Diff Scanner`
- **Subagent Type**: `research` or `self`
- **Task Prompt**:
  > Run `git diff --name-only origin/[BASE_BRANCH]...HEAD`. Map all touched files into Backend Core (Models, Migrations, Actions, Services), HTTP/Auth (Controllers, Requests, Policies, Routes), Frontend (Blade Views, JS), and Tests (PHPUnit, Dusk). Read relevant file contents and line ranges. Return a file mapping dictionary with file paths and line ranges for each component.

- **File Grouping Output**:
  - **Backend Core**: `app/Models/`, `database/migrations/`, `app/Actions/`, `app/Services/`.
  - **HTTP & Auth**: `app/Http/Controllers/`, `app/Http/Requests/`, `app/Policies/`, `routes/web.php`.
  - **Frontend**: `resources/views/`, `resources/js/`, `resources/css/`.
  - **Automated Tests**: `tests/Feature/`, `tests/Unit/`, `tests/Browser/`.

---

### Step 3: Parallel Matrix Audit (`Subagents: Requirement Auditors`)

Dispatch **two subagents in parallel** using `invoke_subagent` to audit functional and non-functional requirements concurrently without shared state.

#### Subagent A: Functional & Business Rules Auditor
- **Role**: `Functional & Business Rules Auditor`
- **Subagent Type**: `research` or `self`
- **Task Prompt**:
  > Compare extracted RFs and RNs from Step 1 against the code files mapped in Step 2. Verify if every RF has an active route, controller action, view, and test. Verify if every RN is strictly enforced in backend validation, Form Requests, and Policies. Return status (`PASS`, `PARTIAL`, `FAIL`), evidence file links (`file:///...#Lxx`), and gap descriptions.

#### Subagent B: Security, Multitenancy & RNF Auditor
- **Role**: `Security & Multitenancy Auditor`
- **Subagent Type**: `research` or `self`
- **Task Prompt**:
  > Audit code for Non-Functional Requirements (RNF). Check:
  > 1. Tenant Isolation: Does every model use `OrgScope` or inherit org context? Is `org_id` enforced on queries and uploads (`storage/app/public/orgs/{org_id}/...`)?
  > 2. Authorization: Are all routes protected by Policies (`$this->authorize()`) and role middleware?
  > 3. Security: Are passwords/tokens masked as `[REDACTED]` in logs? Is HTML output sanitized against XSS?
  > 4. Audit Logging: Are mutations tracked via `AuditableTrait` or `AuditService::log()`?
  > Return status (`PASS`, `PARTIAL`, `FAIL`), evidence links, and security gap findings.

---

### Step 4: Test Suite & Quality Gate (`Subagent: Test Suite Verifier`)

Dispatch a subagent to verify test suite execution and test quality.

- **Role**: `Test Suite Verifier`
- **Subagent Type**: `research` or `self`
- **Task Prompt**:
  > Run PHPUnit and Dusk test suites related to this spec (`vendor/bin/sail artisan test --filter=...`). Verify:
  > 1. Tests are written as PHPUnit test classes extending `Tests\TestCase` (NOT Pest functions).
  > 2. Happy paths, 403 authorization failures, 422 validation failures, and multi-tenant isolation scenarios are covered.
  > 3. Interactive UI flows (AJAX reorder, modals) have Dusk E2E browser tests using `dusk="..."` selectors.
  > 4. No empty assertions or fake tests exist.
  > Return test execution status, pass/fail counts, and missing test scenarios.

---

### Step 5: Report Synthesis & File Generation (`Subagent: Report Synthesizer`)

Dispatch a subagent to compile findings from all previous steps and generate the final report file at `spec/reviews/spec_xx_review_report.md`.

- **Role**: `Review Report Synthesizer`
- **Subagent Type**: `self` or write directly in orchestrator
- **Task Prompt**:
  > Synthesize results from Step 1-4 subagents. Ensure `spec/reviews/` directory exists. Extract spec number `xx` (e.g. `05` for `05-courses...md`). Generate `spec/reviews/spec_xx_review_report.md` following the mandatory markdown structure. Include executive summary, compliance matrix table with `file:///...` links, detailed unmet requirements, test coverage audit, and prioritized action plan.

---

## Mandatory Report Markdown Structure (`spec/reviews/spec_xx_review_report.md`)

```markdown
# Reporte de Review de Especificação: [Nome da Spec]

- **Data da Revisão:** [YYYY-MM-DD]
- **Branch Analisada:** [nome_da_branch]
- **Arquivo de Spec:** `spec/specs/[nome_do_arquivo].md`
- **Status Geral:** `[COMPLIANT | PARTIALLY_COMPLIANT | NON_COMPLIANT]`
- **Taxa de Cobertura de Requisitos:** [X]% ([Y]/[Z] requisitos atendidos)

---

## 1. Resumo Executivo

[Resumo explicativo de 1-2 parágrafos apresentando o diagnóstico geral da implementação da spec no código atual, destacando conformidades principais, débitos técnicos e riscos de segurança/negócio.]

---

## 2. Matriz de Conformidade de Requisitos

| ID | Requisito / Regra | Categoria | Status | Arquivo / Código de Evidência | Lacunas / Observações |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **RF06** | CRUD Cursos/Módulos + Reordenação AJAX | Funcional | `PASS` | [`CourseController.php:L25-L60`](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/CourseController.php#L25-L60) | Atendido completamente. |
| **RN14** | Mascaramento de Senha [REDACTED] | Regra Negócio | `FAIL` | [`AuditService.php:L40`](file:///home/rafael/projects/cursos/plataforma_ead/app/Services/AuditService.php#L40) | Senha gravada em texto plano no log. |
| **RNF01** | Isolamento Multitenant `OrgScope` | Não-Funcional | `PASS` | [`Course.php:L12`](file:///home/rafael/projects/cursos/plataforma_ead/app/Models/Course.php#L12) | Trait OrgScope aplicada no Model. |

*(Legenda: `PASS` = Totalmente Atendido | `PARTIAL` = Parcialmente Atendido | `FAIL` = Não Atendido / Com Falhas)*

---

## 3. Detalhamento de Requisitos Incompletos / Não Atendidos

### 3.1. [ID Requisito] - [Título do Requisito]
- **Descrição na Spec:** [Citação textual do requisito]
- **Estado Atual no Código:** [Descrição técnica detalhada de como o código se comporta atualmente]
- **Evidência:** [`app/Services/ExampleService.php:L45-L60`](file:///home/rafael/projects/cursos/plataforma_ead/app/Services/ExampleService.php#L45-L60)
- **Impacto / Risco:** [Descrição do impacto funcional, de segurança ou de regras de negócio]
- **Ação Corretiva Necessária:** [Passos claros e acionáveis para corrigir a implementação no código]

---

## 4. Auditoria de Testes Automatizados (PHPUnit & Dusk E2E)

- **Testes Backend (PHPUnit):** `[PASS | FAIL]` - [Detalhamento de testes existentes vs. faltantes]
- **Testes Browser (Dusk E2E):** `[PASS | FAIL]` - [Detalhamento de testes Dusk existentes vs. faltantes]
- **Lacunas de Cobertura de Testes:**
  - [ ] `tests/Feature/ExampleTest.php`: Adicionar teste para o cenário de falha X.
  - [ ] `tests/Browser/ExampleDuskTest.php`: Criar teste Dusk para fluxo Y.

---

## 5. Plano de Ação & Recomendações de Correção

1. **[Prioridade Alta - Bloqueante]**: [Ação 1]
2. **[Prioridade Média]**: [Ação 2]
3. **[Prioridade Baixa]**: [Ação 3]
```

---

## Red Flags & Common Mistakes During Review

- 🚩 **Not Dispatching Subagents**: Executing all steps in the main conversation thread, causing context truncation and incomplete analysis.
- 🚩 **Superficial Verification**: Marking a requirement as `PASS` just because a controller method or view file exists, without checking if validation, policies, tenant isolation, and tests are implemented.
- 🚩 **Ignoring Non-Functional Rules**: Focusing only on happy-path UI while ignoring tenant isolation (`OrgScope`), password masking (`[REDACTED]`), XSS sanitization, or file upload storage paths (`storage/app/public/orgs/{org_id}/...`).
- 🚩 **Missing Dusk Test Verification**: Marking UI-heavy specs (e.g. AJAX reordering, dynamic modals, rich text editors) as compliant when Dusk E2E browser tests are missing.
- 🚩 **Unlinked Code References**: Referencing code files without using proper `file:///` markdown links.
- 🚩 **Forgetting the Output File**: Conducting the review in text form without saving the report file to `spec/reviews/spec_xx_review_report.md`.

