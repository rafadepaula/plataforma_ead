---
name: basic-spec-implementer
description: Take spec task from spec/specs/, tech-refine against codebase, implement TDD-first with laravel-tdd RED-GREEN-REFACTOR cycle plus laravel-dusk skill for browser flows, PHPUnit classes per project convention, verify full suite, loop code-reviewer until clean, then check module skills for staleness.
---

# Basic Spec Implementer (`basic-spec-implementer`)

## Overview

`basic-spec-implementer` = end-to-end spec task implementation skill. Take requirement from `spec/specs/` (e.g. `spec/specs/01-quizzes.md` or task ref like `RF01`). Do deep technical analysis against codebase. Split implementation into 3 parallel TDD work buckets using PHPUnit and Laravel Dusk. Run full test suite verification. Loop automated code review with `code-reviewer`. Finish by updating module skill triad per SPEC-03.

---

## When to Use

Use `basic-spec-implementer` when task = implement spec requirement from `spec/specs/`.

**Invocation Arguments**:

- `specFile`: Path or filename of spec in `spec/specs/` (e.g., `01-quizzes.md` or `spec/specs/01-quizzes.md`).
- `taskRef`: (Optional) Requirement or task ref (e.g., `RF01` or `RF02`). If omitted, defaults to whole spec file.

---

## The 6-Phase Pipeline Workflow

```
[Phase 1: Understand] ──► [Phase 2: Tech-Refine] ──► [Phase 3: Code (Parallel)]
                                                              │
[Phase 6: Meta-Skill-Check] ◄── [Phase 5: Review] ◄── [Phase 4: Test]
```

---

### Phase 1: Understand (`spec-understand-agent`)

- **Objective**: Analyze spec file. Extract business rules, DB impacts, acceptance criteria, cross-spec dependencies.
- **Constraint**: **Research pass ONLY**. Do NOT write, edit, delete any code or tests.
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
            "acceptanceCriteria": {
                "type": "array",
                "items": { "type": "string" }
            },
            "relatedSpecs": { "type": "array", "items": { "type": "string" } }
        },
        "required": ["requirementText", "businessRules", "acceptanceCriteria"]
    }
    ```

---

### Phase 2: Tech-Refine (`spec-tech-refine-agent`)

- **Objective**: Study current codebase state (migrations in `database/migrations`, models in `app/Models`, controllers/actions, routes, existing tests). Design technical plan split into **EXACTLY 3 independent work buckets**.
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
                        "files": {
                            "type": "array",
                            "items": { "type": "string" }
                        }
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

- **Objective**: Implement 3 work buckets concurrently with TDD and Dusk.
- **Subagents**: Invoke up to 3 parallel instances of `spec-coder-agent`, each assigned one bucket from Phase 2.
- **Directives & Guardrails**:
    1. **Strict Bucket Scoping**: Each agent touches ONLY files listed for its bucket.
    2. **PHPUnit TDD Mandate**:
        - Apply `laravel-tdd` RED-GREEN-REFACTOR cycle.
        - **MUST write tests as PHPUnit test classes** (extending `Tests\TestCase`), NOT Pest function syntax (project CLAUDE.md mandates PHPUnit classes).
        - Write failing test method first (RED) → verify failure with `vendor/bin/sail artisan test --filter=testMethodName` → write minimal implementation (GREEN) → verify pass → refactor keeping tests green.
        - Exception: view-only markup or simple config changes need no RED test first.
    3. **Laravel Dusk for UI/Browser Flows**:
        - For Blade views, JS interactions, browser workflows (per spec 00 §5 mandatory Dusk coverage), use `laravel-dusk`.
        - Write PHPUnit-style Browser tests in `tests/Browser` (extending `DuskTestCase`).
        - **Group by lifecycle chain, not by module/use case**: before creating new Browser file, search `tests/Browser/` for chain already covering this journey and **extend it**. One method drives create → edit → state change → delete → consequence, with UI assertion **and** DB assertion per step (numbered `// 1.` step comments). Independent negatives (403, cross-tenant, other actor) stay in own methods. Full rule in `testing-conventions`.
        - **No DB trait in test class** — `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Adding `DatabaseMigrations` back = performance regression; `RefreshDatabase` forbidden (Dusk runs in separate HTTP process).
        - Explicit `waitFor` over fixed sleep/pause.
        - Budget: ≤ 1 `loginAs()` per method; file with > ~6 methods signals fragmentation.
    4. **Formatting**:
        - Run `vendor/bin/sail bin pint --dirty --format agent` on all modified PHP files.

---

### Phase 4: Test (`spec-tester-agent`)

- **Objective**: Execute full test suite, verify coverage, audit edge cases, ensure no regressions.
- **Subagent**: Invoke `spec-tester-agent`.
- **Verification Checklists**:
    - `laravel-tdd` checklist: migrations, model relationships, controllers/API integration, validation, authorization, `RefreshDatabase` for Unit/Feature, factories used.
    - `laravel-dusk` checklist: no DB trait redeclared in `tests/Browser/*` (`grep -rn "DatabaseMigrations\|RefreshDatabase" tests/Browser/` must be empty), new browser coverage added as lifecycle chain rather than new atomic methods, ChromeDriver updated (`vendor/bin/sail artisan dusk:chrome-driver --detect`), screenshots checked in `tests/Browser/screenshots` on failure.
    - Audit `edgeCases` from Phase 2 plan; write any missing test.
- **Commands**:
    ```bash
    vendor/bin/sail artisan test --compact
    vendor/bin/sail artisan dusk:chrome-driver --detect
    vendor/bin/sail artisan dusk
    php scripts/check-coverage.php
    ```
- **Reporting**: Report exact pass/fail counts per suite plus coverage percentage. If anything fails, fix and re-run.

---

### Phase 5: Review (`code-reviewer`, `validate-test-quality`, `spec-usecase-test-checker`, & `spec-fixer-agent` Loop)

- **Objective**: Run automated code review with `code-reviewer`, validate quality and efficacy of implemented tests with `validate-test-quality`, revalidate Use Case E2E Dusk test coverage with `spec-usecase-test-checker`, fix confirmed issues with `spec-fixer-agent`, iterate (capped at 6 iterations).
- **Subagents / Skills**: Invoke `code-reviewer` and `spec-usecase-test-checker` agents. Apply `validate-test-quality` skill rules.
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
                        "verdict": {
                            "type": "string",
                            "enum": ["CONFIRMED", "PLAUSIBLE"]
                        }
                    },
                    "required": ["file", "summary", "verdict"]
                }
            }
        },
        "required": ["findings"]
    }
    ```
- **Review Loop Protocol**:
    1. `code-reviewer` audits code diff using `laravel-best-practices`, `laravel-specialist`, `laravel-verification` (plus tenancy skills if org-scoped).
    2. **Test Quality Audit (`validate-test-quality`)**: Audit all implemented PHPUnit and Dusk tests against 6 Pillars of Real Test Validation:
        - **SUT Integrity**: Real execution of concrete SUT (0% SUT mocking).
        - **Assertion Meaningfulness**: No tautological checks (`assertTrue(true)`) or weak factory checks (`assertNotNull`).
        - **Mutation Resiliency**: Tests fail if production logic mutated/broken.
        - **State Verification**: DB values (`assertDatabaseHas`), JSON responses, side-effects.
        - **Mandatory Failure Paths**: Must cover 403 Forbidden, 422 Validation, Exception guards (`UnresolvedOrgContextException`), cross-tenant data leaks.
        - **Refactor Resilience**: Tests outcomes and contract behavior, not internal helper method execution order.
          _Any test quality defect, fake assertion, coverage padding, or missing failure path recorded as `CONFIRMED` finding._
    3. **UseCase E2E Test Audit (`spec-usecase-test-checker`)**: Invoke `spec-usecase-test-checker` to inspect all Use Cases (UCs) tied to `${SPEC_FILE}` (from `spec/docs/usecases/` and `spec/specs/`) and revalidate in codebase (`tests/Browser/`) whether **EVERY** Use Case has success and failure/exception scenarios asserted in E2E Laravel Dusk. Coverage counted **per assertion set, not per method or file**: UC covered when its steps asserted somewhere inside lifecycle chain, even if that chain lives in file named after another module. **Never** record "UC has no dedicated test method/file" as finding — only genuinely missing scenario path (no UI/DB assertion for it anywhere) is `CONFIRMED` finding.
    4. Filter findings where `verdict === "CONFIRMED"`.
    5. If 0 confirmed findings, break loop (clean review).
    6. If confirmed findings exist and iteration < 6:
        - Launch `spec-fixer-agent` to fix all listed `CONFIRMED` code, test quality, and missing Use Case E2E Dusk test findings directly in code.
        - Re-run relevant tests to confirm fix without scope creep.
        - Repeat review pass (up to 6 iterations max).

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
    4. Check if project-level skills (`laravel-tdd`, `laravel-dusk`) need narrow project-specific notes added (ONLY if real gap was encountered).
- **Output Schema (`SKILL_CHECK_SCHEMA`)**:
    ```json
    {
        "type": "object",
        "properties": {
            "skillsReviewed": {
                "type": "array",
                "items": { "type": "string" }
            },
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

## Summary of Associated Subagents

| Subagent                        | Phase   | Role                                                  |
| :------------------------------ | :------ | :---------------------------------------------------- |
| `spec-understand-agent`         | Phase 1 | Read-only requirement analysis & JSON extraction      |
| `spec-tech-refine-agent`        | Phase 2 | Codebase exploration & 3-bucket architecture planning |
| `spec-coder-agent`              | Phase 3 | Bucket-scoped TDD implementation (PHPUnit + Dusk)     |
| `spec-tester-agent`             | Phase 4 | Full test suite verification & coverage auditing      |
| `code-reviewer`                 | Phase 5 | Static analysis & code review audit                   |
| `spec-usecase-test-checker`     | Phase 5 | Revalidates E2E Dusk coverage for all spec Use Cases  |
| `spec-fixer-agent`              | Phase 5 | Auto-remediation of `CONFIRMED` review findings       |
| `spec-meta-skill-checker-agent` | Phase 6 | SPEC-03 module skill triad staleness update           |

---

## Core Conventions & Guardrails

- **PHP 8.5 & Laravel Sail**: Prefix all PHP, Artisan, Composer, Pint commands with `vendor/bin/sail`.
- **PHPUnit Over Pest**: Project `CLAUDE.md` mandates PHPUnit test classes (`class FooTest extends TestCase`). Never use Pest function syntax (`test(...)`).
- **Laravel Dusk Isolation**: Dusk tests run in separate HTTP process. `DatabaseTruncation` inherited from `Tests\DuskTestCase` — declare no DB trait in `tests/Browser/*`. Never use `RefreshDatabase` in Dusk; `DatabaseMigrations` retired (per-method `migrate:fresh`).
- **E2E Grouping**: browser coverage organized by lifecycle chain (user journey), never by module/spec/use case. Extend existing chain before creating new test file.
- **Pint Formatting**: Always format modified PHP files with `vendor/bin/sail bin pint --dirty --format agent`.
