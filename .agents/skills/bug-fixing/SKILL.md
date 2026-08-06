---
name: bug-fixing
description: Use when resolving a bug specified in spec/bugs/BUG-{id}-{slug}.md, requiring TDD-first reproduction test creation, minimal fix implementation using PHPUnit and Laravel Dusk, automated code review, and regression suite verification.
---

# Bug Fixing Skill (`bug-fixing`)

## Overview

`bug-fixing` is an end-to-end bug resolution skill. It takes a bug specification file from `spec/bugs/BUG-{id}-{slug}.md`, performs deep technical analysis against the codebase, writes a TDD test reproducing the exact failure (RED phase), implements the minimal fix (GREEN phase), verifies zero regressions across the suite, loops automated code review with `code-reviewer`, and marks the bug report as resolved while updating module skills per SPEC-03.

---

## When to Use

Use `bug-fixing` whenever you are assigned to fix a bug that has a specification file in `spec/bugs/`.

**Invocation Arguments**:
- `bugReportFile`: Path to the bug specification file (e.g. `spec/bugs/BUG-001-quiz-score-calculation.md`).

---

## The 6-Phase Pipeline Workflow

```
[Phase 1: Bug Analysis] ──► [Phase 2: Tech-Refine] ──► [Phase 3: TDD Fix Cycle]
                                                                  │
[Phase 6: Sign-off & Skills] ◄── [Phase 5: Review] ◄── [Phase 4: Test & Regressions]
```

---

### Phase 1: Bug Analysis (`spec-understand-agent`)

- **Objective**: Read `spec/bugs/BUG-{id}-{slug}.md` in full to understand the bug, affected roles, tenant context (`org_id`), step-by-step reproduction guide, actual vs expected behavior, and root cause hypothesis.
- **Constraint**: **Research pass ONLY**. Do NOT write, edit, or delete any code or tests.
- **Subagent**: Invoke `spec-understand-agent`.
- **Inputs**: `bugReportFile`.
- **Reference Docs**: Read target `BUG-{id}-{slug}.md`, plus related feature specs in `spec/specs/` and `spec/specs/00-architecture-database-and-guardrails.md`.
- **Output Schema (`BUG_UNDERSTANDING_SCHEMA`)**:
    ```json
    {
        "type": "object",
        "properties": {
            "bugId": { "type": "string" },
            "summary": { "type": "string" },
            "affectedRole": { "type": "string" },
            "tenantContext": { "type": "string" },
            "reproductionSteps": { "type": "array", "items": { "type": "string" } },
            "expectedBehavior": { "type": "string" },
            "actualBehavior": { "type": "string" },
            "mappedFiles": { "type": "array", "items": { "type": "string" } },
            "rootCauseHypothesis": { "type": "string" }
        },
        "required": ["bugId", "summary", "reproductionSteps", "expectedBehavior", "actualBehavior"]
    }
    ```

---

### Phase 2: Technical Refinement & Reproduction Plan (`spec-tech-refine-agent`)

- **Objective**: Inspect the codebase at mapped files (`app/Controllers`, `app/Models`, `app/Services`, `database/migrations`, `tests/`) and design a precise TDD fix plan.
- **Subagent**: Invoke `spec-tech-refine-agent`.
- **Plan Structure**:
    1. **Reproduction Test Target**: Define exact test file and method name to create/extend in `tests/Feature/` or `tests/Browser/`.
    2. **Minimal Fix Scope**: List exact files to modify to fix the root cause without introducing side effects or scope creep.
    3. **Regression Risk Scopes**: Identify related modules or tenant boundaries that could be affected.
- **Output Schema (`BUG_FIX_PLAN_SCHEMA`)**:
    ```json
    {
        "type": "object",
        "properties": {
            "testType": { "type": "string", "enum": ["PHPUnit", "Dusk", "Both"] },
            "reproductionTestFile": { "type": "string" },
            "reproductionTestMethod": { "type": "string" },
            "targetFixFiles": { "type": "array", "items": { "type": "string" } },
            "regressionRiskScopes": { "type": "array", "items": { "type": "string" } }
        },
        "required": ["testType", "reproductionTestFile", "reproductionTestMethod", "targetFixFiles"]
    }
    ```

---

### Phase 3: TDD Fix Cycle (`spec-coder-agent`)

- **Objective**: Execute the TDD RED-GREEN-REFACTOR cycle to reproduce and fix the bug.
- **Subagent**: Invoke `spec-coder-agent`.
- **Mandatory TDD Cycle Steps**:
    1. **RED Phase (Write Failing Test)**:
       - Write a PHPUnit test class (extending `Tests\TestCase`) or Dusk Browser test class (extending `DuskTestCase`) that reproduces the exact failure steps from `BUG-{id}-{slug}.md`.
       - Run test: `vendor/bin/sail artisan test --filter={reproductionTestMethod}` (or `vendor/bin/sail artisan dusk --filter={reproductionTestMethod}`).
       - **VERIFY FAILURE**: Confirm the test fails with the expected error/exception (not a syntax/setup error).
    2. **GREEN Phase (Minimal Code Fix)**:
       - Write the minimal code in the target fix files to resolve the root cause.
       - Re-run test: `vendor/bin/sail artisan test --filter={reproductionTestMethod}`.
       - **VERIFY PASS**: Confirm the reproduction test now passes completely.
    3. **REFACTOR Phase**:
       - Clean up any temporary debug lines or code smells while keeping tests green.
       - Run `vendor/bin/sail bin pint --dirty --format agent` to format modified PHP files.

---

### Phase 4: Test & Regression Suite Verification (`spec-tester-agent`)

- **Objective**: Run full test suites to verify fix and ensure zero regressions across tenant, role, or feature boundaries.
- **Subagent**: Invoke `spec-tester-agent`.
- **Verification Commands**:
    ```bash
    # 1. Run the reproduction test
    vendor/bin/sail artisan test --filter={reproductionTestMethod}

    # 2. Run full feature test suite
    vendor/bin/sail artisan test --compact

    # 3. Run browser tests if UI bug
    vendor/bin/sail artisan dusk:chrome-driver --detect
    vendor/bin/sail artisan dusk
    ```
- **Reporting**: Ensure 100% tests pass. If any pre-existing test broke, adjust fix to preserve contract.

---

### Phase 5: Code & Test Quality Review (`code-reviewer`, `validate-test-quality`, & `spec-fixer-agent` Loop)

- **Objective**: Audit the bug fix diff and reproduction test quality, correcting any findings.
- **Subagents**: Invoke `code-reviewer`, apply `validate-test-quality` skill, and invoke `spec-fixer-agent` if needed (max 6 iterations).
- **Audit Directives**:
    - **Code Review**: Check against `laravel-best-practices` and `tenancy-security` (no cross-tenant leaks, proper `OrgScope` handling).
    - **Test Quality (`validate-test-quality`)**: Verify the reproduction test actually tests the SUT (0% fake assertions, real DB verification).
- **Fix Loop**:
    - Filter `verdict === "CONFIRMED"`.
    - If 0 confirmed findings: review clean.
    - If confirmed findings exist: launch `spec-fixer-agent` to apply fixes, then re-verify tests.

---

### Phase 6: Meta-Skill-Check & Bug Resolution Sign-Off (`spec-meta-skill-checker-agent`)

- **Objective**: Mark bug report as resolved and update module documentation per SPEC-03.
- **Subagent**: Invoke `spec-meta-skill-checker-agent`.
- **Sign-Off Protocol**:
    1. Update `spec/bugs/BUG-{id}-{slug}.md` status to `[RESOLVED]`:
       ```markdown
       ## Resolution Status
       - **Status:** RESOLVED
       - **Reproduction Test:** `tests/Feature/...` (`test_method_name`)
       - **Fixed In Files:** `app/...`
       ```
    2. Audit module skill triad (`.agents/skills/{module}-architecture`, `{module}-conventions`, `{module}-maintenance`).
    3. If root cause revealed an architectural edge case or maintenance gotcha, add it to `{module}-maintenance/SKILL.md` to prevent recurrence.

---

## 🛠️ Summary of Associated Subagents

| Subagent                        | Phase   | Role                                                  |
| :------------------------------ | :------ | :---------------------------------------------------- |
| `spec-understand-agent`         | Phase 1 | Reads bug specification file and extracts metadata    |
| `spec-tech-refine-agent`        | Phase 2 | Explores codebase and builds TDD reproduction plan    |
| `spec-coder-agent`              | Phase 3 | Executes TDD RED-GREEN-REFACTOR fix cycle             |
| `spec-tester-agent`             | Phase 4 | Full test suite execution & regression verification   |
| `code-reviewer`                 | Phase 5 | Code & test quality review audit                      |
| `spec-fixer-agent`              | Phase 5 | Auto-remediation of confirmed review findings         |
| `spec-meta-skill-checker-agent` | Phase 6 | Updates bug resolution status & module skill triad    |

---

## ⚠️ Core Guardrails & Conventions

- **TDD Mandatory**: Never write fix code before watching the reproduction test fail (RED phase).
- **PHPUnit Over Pest**: All PHPUnit tests must use PHPUnit test classes (`class BugFixTest extends TestCase`), per project convention in CLAUDE.md.
- **Dusk Isolation**: Never use `RefreshDatabase` in Dusk tests; use `DatabaseMigrations` or `DatabaseTruncation`.
- **Sail Execution**: All execution commands must be prefixed with `vendor/bin/sail`.
- **Pint Formatting**: Format touched PHP files with `vendor/bin/sail bin pint --dirty --format agent`.
