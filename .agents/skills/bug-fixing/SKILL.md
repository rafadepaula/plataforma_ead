---
name: bug-fixing
description: Use when resolving a bug specified in spec/bugs/BUG-{id}-{slug}.md, requiring understanding the bug then fixing it TDD-first with a PHPUnit unit/integration test, or a Dusk test if it is a UI bug.
---

# Bug Fixing Skill (`bug-fixing`)

## Overview

`bug-fixing` is a lean, two-phase bug resolution skill. It reads a bug specification file from `spec/bugs/BUG-{id}-{slug}.md`, then fixes it with a strict TDD RED-GREEN-REFACTOR cycle: a failing PHPUnit unit/integration test (or a Dusk browser test if it's a UI bug), then the minimal fix to make it pass.

---

## When to Use

Use `bug-fixing` whenever you are assigned to fix a bug that has a specification file in `spec/bugs/`.

**Invocation Arguments**:
- `bugReportFile`: Path to the bug specification file (e.g. `spec/bugs/BUG-001-quiz-score-calculation.md`).

---

## The 2-Phase Pipeline Workflow

```
[Phase 1: Understand] ──► [Phase 2: TDD Fix]
```

---

### Phase 1: Understand (`spec-understand-agent`)

- **Objective**: Read `spec/bugs/BUG-{id}-{slug}.md` in full to understand the bug: reproduction steps, expected vs actual behavior, root cause hypothesis, and whether it is a UI bug.
- **Constraint**: **Research pass ONLY**. Do NOT write, edit, or delete any code or tests.
- **Subagent**: Invoke `spec-understand-agent`.
- **Inputs**: `bugReportFile`.
- **Output Schema (`BUG_UNDERSTANDING_SCHEMA`)**:
    ```json
    {
        "type": "object",
        "properties": {
            "bugId": { "type": "string" },
            "summary": { "type": "string" },
            "isUiBug": { "type": "boolean" },
            "reproductionSteps": { "type": "array", "items": { "type": "string" } },
            "expectedBehavior": { "type": "string" },
            "actualBehavior": { "type": "string" },
            "mappedFiles": { "type": "array", "items": { "type": "string" } },
            "rootCauseHypothesis": { "type": "string" }
        },
        "required": ["bugId", "summary", "isUiBug", "reproductionSteps", "expectedBehavior", "actualBehavior"]
    }
    ```

---

### Phase 2: TDD Fix (`spec-coder-agent`)

- **Objective**: Execute the TDD RED-GREEN-REFACTOR cycle to reproduce and fix the bug.
- **Subagent**: Invoke `spec-coder-agent`.
- **Test type choice**: Dusk ONLY if `isUiBug` is true and the bug cannot be reproduced without a real browser. Otherwise a PHPUnit Unit test (isolated logic) or Feature/integration test (HTTP, database, multiple collaborators).
- **Mandatory TDD Cycle Steps**:
    1. **RED Phase (Write Failing Test)**:
       - Write a PHPUnit test class (extending `Tests\TestCase`) or Dusk Browser test class (extending `DuskTestCase`) reproducing the exact steps from `BUG-{id}-{slug}.md`.
       - Run: `vendor/bin/sail artisan test --filter={testMethod}` (or `vendor/bin/sail artisan dusk --filter={testMethod}` for Dusk).
       - **VERIFY FAILURE**: Confirm the test fails with the expected error/exception (not a syntax/setup error).
    2. **GREEN Phase (Minimal Code Fix)**:
       - Write the minimal code to resolve the root cause. No scope creep.
       - Re-run the same filtered command.
       - **VERIFY PASS**: Confirm the test now passes completely.
    3. **REFACTOR Phase**:
       - Clean up any temporary debug lines while keeping the test green.
       - Run `vendor/bin/sail bin pint --dirty --format agent` to format modified PHP files.
- **Output Schema (`TDD_FIX_SCHEMA`)**:
    ```json
    {
        "type": "object",
        "properties": {
            "testType": { "type": "string", "enum": ["PHPUnit", "Dusk"] },
            "testFile": { "type": "string" },
            "testMethod": { "type": "string" },
            "redObservedFailure": { "type": "string" },
            "filesModified": { "type": "array", "items": { "type": "string" } },
            "fixSummary": { "type": "string" },
            "testPasses": { "type": "boolean" }
        },
        "required": ["testType", "testFile", "testMethod", "redObservedFailure", "filesModified", "testPasses"]
    }
    ```

---

## 🛠️ Summary of Associated Subagents

| Subagent                 | Phase   | Role                                              |
| :------------------------ | :------ | :------------------------------------------------- |
| `spec-understand-agent`   | Phase 1 | Reads bug specification file and extracts metadata |
| `spec-coder-agent`        | Phase 2 | Executes TDD RED-GREEN-REFACTOR fix cycle          |

---

## ⚠️ Core Guardrails & Conventions

- **TDD Mandatory**: Never write fix code before watching the reproduction test fail (RED phase).
- **PHPUnit Over Pest**: All PHPUnit tests must use PHPUnit test classes (`class BugFixTest extends TestCase`), per project convention in CLAUDE.md.
- **Dusk Isolation**: Never use `RefreshDatabase` in Dusk tests; use `DatabaseMigrations` or `DatabaseTruncation`.
- **Sail Execution**: All execution commands must be prefixed with `vendor/bin/sail`.
- **Pint Formatting**: Format touched PHP files with `vendor/bin/sail bin pint --dirty --format agent`.
