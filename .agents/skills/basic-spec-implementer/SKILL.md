---
name: basic-spec-implementer
description: Understand a spec task from spec/specs/, tech-refine it against the current codebase, implement it TDD-first via the laravel-tdd RED-GREEN-REFACTOR cycle and the laravel-dusk skill for browser flows, using PHPUnit classes per project convention, verify the full suite, loop code-reviewer until clean, then check the module skills for staleness.
---

# Basic Spec Implementer (`basic-spec-implementer`)

## Overview

`basic-spec-implementer` is an end-to-end specification task implementation skill. It takes a requirement from `spec/specs/` (e.g. `spec/specs/01-quizzes.md` or a specific task reference like `RF01`), performs deep technical analysis against the codebase, decomposes implementation into 3 parallel TDD work buckets using PHPUnit and Laravel Dusk, runs full test suite verification, loops automated code review with `code-reviewer`, and finishes by updating the module's skill triad per SPEC-03.

---

## When to Use

Use `basic-spec-implementer` whenever you are tasked with implementing a specification requirement from `spec/specs/`. 

**Invocation Arguments**:
- `specFile`: Path or filename of the spec in `spec/specs/` (e.g., `01-quizzes.md` or `spec/specs/01-quizzes.md`).
- `taskRef`: (Optional) Specific requirement or task reference (e.g., `RF01` or `RF02`). If omitted, defaults to the entire spec file.

---

## The 6-Phase Pipeline Workflow

```
[Phase 1: Understand] ──► [Phase 2: Tech-Refine] ──► [Phase 3: Code (Parallel)]
                                                              │
[Phase 6: Meta-Skill-Check] ◄── [Phase 5: Review] ◄── [Phase 4: Test]
```

---

### Phase 1: Understand (`spec-understand-agent`)

- **Objective**: Analyze the specification file and extract business rules, database impacts, acceptance criteria, and cross-spec dependencies.
- **Constraint**: **Research pass ONLY**. Do NOT write, edit, or delete any code or tests.
- **Subagent**: Invoke `spec-understand-agent`.
- **Inputs**: `specFile`, `taskRef`.
- **Reference Docs**: Read `spec/specs/${SPEC_FILE}` in full, plus `spec/specs/00-architecture-database-and-guardrails.md` and `spec/specs/README.md`.
- **Output Schema (`UNDERSTANDING_SCHEMA`)**:
  ```json
  {
    "type": "object",
    "properties": {
      "requirementText": { "type": "string" },
      "businessRules": { "type": "array", "items": { "type": "string" } },
      "dbTables": { "type": "array", "items": { "type": "string" } },
      "acceptanceCriteria": { "type": "array", "items": { "type": "string" } },
      "relatedSpecs": { "type": "array", "items": { "type": "string" } }
    },
    "required": ["requirementText", "businessRules", "acceptanceCriteria"]
  }
  ```

---

### Phase 2: Tech-Refine (`spec-tech-refine-agent`)

- **Objective**: Study the current codebase state (migrations in `database/migrations`, models in `app/Models`, controllers/actions, routes, and existing tests) and design a technical plan split into **EXACTLY 3 independent work buckets**.
- **Subagent**: Invoke `spec-tech-refine-agent`.
- **Inputs**: Phase 1 understanding metadata, `specFile`, `taskRef`.
- **Guidelines**: Apply `laravel-best-practices` conventions (idiomatic Eloquent, form requests, policies, single-responsibility actions).
- **Output Schema (`TECH_PLAN_SCHEMA`)**:
  ```json
  {
    "type": "object",
    "properties": {
      "buckets": {
        "type": "array",
        "items": {
          "type": "object",
          "properties": {
            "name": { "type": "string" },
            "description": { "type": "string" },
            "files": { "type": "array", "items": { "type": "string" } }
          },
          "required": ["name", "description", "files"]
        }
      },
      "edgeCases": { "type": "array", "items": { "type": "string" } },
      "openQuestions": { "type": "array", "items": { "type": "string" } }
    },
    "required": ["buckets"]
  }
  ```

---

### Phase 3: Code (`spec-coder-agent` - Up to 3 Parallel Instances)

- **Objective**: Implement the 3 work buckets concurrently using TDD and Dusk.
- **Subagents**: Invoke up to 3 parallel instances of `spec-coder-agent`, each assigned one bucket from Phase 2.
- **Directives & Guardrails**:
  1. **Strict Bucket Scoping**: Each agent touches ONLY the files listed for its bucket.
  2. **PHPUnit TDD Mandate**:
     - Apply `laravel-tdd` RED-GREEN-REFACTOR cycle.
     - **MUST write tests as PHPUnit test classes** (extending `Tests\TestCase`), NOT Pest function syntax (project CLAUDE.md mandates PHPUnit classes).
     - Write failing test method first (RED) → verify failure with `vendor/bin/sail artisan test --filter=testMethodName` → write minimal implementation (GREEN) → verify pass → refactor keeping tests green.
     - Exception: View-only markup or simple configuration changes do not require RED test first.
  3. **Laravel Dusk for UI/Browser Flows**:
     - For Blade views, JS interactions, or browser workflows (per spec 00 §5 mandatory Dusk coverage), use `laravel-dusk`.
     - Write PHPUnit-style Browser tests in `tests/Browser` (extending `DuskTestCase`).
     - Use `DatabaseMigrations` or `DatabaseTruncation` traits in Dusk tests (**NEVER** `RefreshDatabase`, as Dusk runs in a separate HTTP process).
     - Explicit `waitFor` over fixed sleep/pause.
  4. **Formatting**:
     - Run `vendor/bin/sail bin pint --dirty --format agent` on all modified PHP files.

---

### Phase 4: Test (`spec-tester-agent`)

- **Objective**: Execute full test suite, verify coverage, audit edge cases, and ensure no regressions.
- **Subagent**: Invoke `spec-tester-agent`.
- **Verification Checklists**:
  - `laravel-tdd` checklist: migrations, model relationships, controllers/API integration, validation, authorization, `RefreshDatabase` for Unit/Feature, factories used.
  - `laravel-dusk` checklist: `DatabaseMigrations`/`DatabaseTruncation` used in Dusk, ChromeDriver updated (`vendor/bin/sail artisan dusk:chrome-driver --detect`), screenshots checked in `tests/Browser/screenshots` on failure.
  - Audit `edgeCases` from Phase 2 plan; write any missing test.
- **Commands**:
  ```bash
  vendor/bin/sail artisan test --compact
  vendor/bin/sail artisan dusk:chrome-driver --detect
  vendor/bin/sail artisan dusk
  php scripts/check-coverage.php
  ```
- **Reporting**: Report exact pass/fail counts per suite and coverage percentage. If anything fails, fix and re-run.

---

### Phase 5: Review (`code-reviewer`, `validate-test-quality`, & `spec-fixer-agent` Loop)

- **Objective**: Conduct automated code review using `code-reviewer`, validate the quality and efficacy of implemented tests using `validate-test-quality`, fix confirmed issues with `spec-fixer-agent`, and iterate (capped at 3 iterations).
- **Subagents / Skills**: Invoke `code-reviewer` agent and apply `validate-test-quality` skill rules.
- **Output Schema (`REVIEW_SCHEMA`)**:
  ```json
  {
    "type": "object",
    "properties": {
      "findings": {
        "type": "array",
        "items": {
          "type": "object",
          "properties": {
            "file": { "type": "string" },
            "line": { "type": "number" },
            "summary": { "type": "string" },
            "failure_scenario": { "type": "string" },
            "verdict": { "type": "string", "enum": ["CONFIRMED", "PLAUSIBLE"] }
          },
          "required": ["file", "summary", "verdict"]
        }
      }
    },
    "required": ["findings"]
  }
  ```
- **Review Loop Protocol**:
  1. `code-reviewer` audits code diff using `laravel-best-practices`, `laravel-specialist`, `laravel-verification` (and tenancy skills if org-scoped).
  2. **Test Quality Audit (`validate-test-quality`)**: Audit all implemented PHPUnit and Dusk tests against the 6 Pillars of Real Test Validation:
     - **SUT Integrity**: Real execution of concrete SUT (0% SUT mocking).
     - **Assertion Meaningfulness**: No tautological checks (`assertTrue(true)`) or weak factory checks (`assertNotNull`).
     - **Mutation Resiliency**: Tests fail if production logic is mutated/broken.
     - **State Verification**: Database values (`assertDatabaseHas`), JSON responses, and side-effects.
     - **Mandatory Failure Paths**: Must cover 403 Forbidden, 422 Validation, Exception guards (`UnresolvedOrgContextException`), and cross-tenant data leaks.
     - **Refactor Resilience**: Tests outcomes and contract behavior, not internal helper method execution order.
     *Any test quality defect, fake assertion, coverage padding, or missing failure path is recorded as a `CONFIRMED` finding.*
  3. Filter findings where `verdict === "CONFIRMED"`.
  4. If 0 confirmed findings, break loop (clean review).
  5. If confirmed findings exist and iteration < 3:
     - Launch `spec-fixer-agent` to fix all listed `CONFIRMED` code and test quality findings directly in code.
     - Re-run relevant tests to confirm fix without scope creep.
     - Repeat review pass (up to 3 iterations max).

---

### Phase 6: Meta-Skill-Check (`spec-meta-skill-checker-agent`)

- **Objective**: SPEC-03 auto-update protocol for module skills.
- **Subagent**: Invoke `spec-meta-skill-checker-agent`.
- **Protocol**:
  1. Identify module from `SPEC_FILE` filename (e.g. `quizzes` from `01-quizzes.md`).
  2. Locate module skill triad in `.agents/skills/`:
     - `{module}-architecture`
     - `{module}-conventions`
     - `{module}-maintenance`
     - Create triad if missing, seeded from what was built.
  3. Audit existing skills against merged code (models, migrations, actions, policies, routes, business rules). Update any stale documentation.
  4. Check if project-level skills (`laravel-tdd`, `laravel-dusk`) need narrow project-specific notes added (ONLY if a real gap was encountered).
- **Output Schema (`SKILL_CHECK_SCHEMA`)**:
  ```json
  {
    "type": "object",
    "properties": {
      "skillsReviewed": { "type": "array", "items": { "type": "string" } },
      "skillsCreated": { "type": "array", "items": { "type": "string" } },
      "skillsUpdated": {
        "type": "array",
        "items": {
          "type": "object",
          "properties": {
            "skill": { "type": "string" },
            "reason": { "type": "string" }
          },
          "required": ["skill", "reason"]
        }
      },
      "noChangeNeeded": { "type": "array", "items": { "type": "string" } }
    },
    "required": ["skillsReviewed"]
  }
  ```

---

## 🛠️ Summary of Associated Subagents

| Subagent | Phase | Role |
| :--- | :--- | :--- |
| `spec-understand-agent` | Phase 1 | Read-only requirement analysis & JSON extraction |
| `spec-tech-refine-agent` | Phase 2 | Codebase exploration & 3-bucket architecture planning |
| `spec-coder-agent` | Phase 3 | Bucket-scoped TDD implementation (PHPUnit + Dusk) |
| `spec-tester-agent` | Phase 4 | Full test suite verification & coverage auditing |
| `code-reviewer` | Phase 5 | Static analysis & code review audit |
| `spec-fixer-agent` | Phase 5 | Auto-remediation of `CONFIRMED` review findings |
| `spec-meta-skill-checker-agent` | Phase 6 | SPEC-03 module skill triad staleness update |

---

## ⚠️ Core Conventions & Guardrails

- **PHP 8.5 & Laravel Sail**: Prefix all PHP, Artisan, Composer, and Pint commands with `vendor/bin/sail`.
- **PHPUnit Over Pest**: Project `CLAUDE.md` mandates PHPUnit test classes (`class FooTest extends TestCase`). Never use Pest function syntax (`test(...)`).
- **Laravel Dusk Isolation**: Dusk tests run in a separate HTTP process. Use `DatabaseMigrations` or `DatabaseTruncation` in Dusk tests. Never use `RefreshDatabase` in Dusk.
- **Pint Formatting**: Always format modified PHP files with `vendor/bin/sail bin pint --dirty --format agent`.
