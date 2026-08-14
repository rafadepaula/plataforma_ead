---
name: bug-fixing
description: Use when resolving bug specified in spec/bugs/BUG-{id}-{slug}.md. Understand bug, then fix TDD-first with PHPUnit unit/integration test, or Dusk test if UI bug.
---

# Bug Fixing Skill (`bug-fixing`)

## Overview

`bug-fixing` is lean two-phase bug resolution skill. Reads bug spec file
from `spec/bugs/BUG-{id}-{slug}.md`, then fixes with strict TDD
RED-GREEN-REFACTOR cycle: failing PHPUnit unit/integration test (or Dusk
browser test if UI bug), then minimal fix to make it pass.

---

## When to Use

Use `bug-fixing` whenever assigned to fix bug that has spec file in
`spec/bugs/`.

**Invocation Arguments**:
- `bugReportFile`: Path to bug spec file (e.g. `spec/bugs/BUG-001-quiz-score-calculation.md`).

---

## The 2-Phase Pipeline Workflow

```
[Phase 1: Understand] ──► [Phase 2: TDD Fix]
```

---

### Phase 1: Understand (`spec-understand-agent`)

- **Objective**: Read `spec/bugs/BUG-{id}-{slug}.md` in full. Get repro steps, expected vs actual behavior, root cause hypothesis, whether UI bug.
- **Constraint**: **Research pass ONLY**. Do NOT write, edit, delete any code or test.
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

- **Objective**: Run TDD RED-GREEN-REFACTOR cycle to reproduce and fix bug.
- **Subagent**: Invoke `spec-coder-agent`.
- **Test type choice**: Dusk ONLY if `isUiBug` true and bug cannot repro without real browser. Else PHPUnit Unit test (isolated logic) or Feature/integration test (HTTP, database, multiple collaborators).
- **Mandatory TDD Cycle Steps**:
    1. **RED Phase (Write Failing Test)**:
       - Write PHPUnit test class (extends `Tests\TestCase`) or Dusk Browser test class (extends `DuskTestCase`) reproducing exact steps from `BUG-{id}-{slug}.md`.
       - Run: `vendor/bin/sail artisan test --filter={testMethod}` (or `vendor/bin/sail artisan dusk --filter={testMethod}` for Dusk).
       - **VERIFY FAILURE**: Confirm test fails with expected error/exception, not syntax/setup error.
    2. **GREEN Phase (Minimal Code Fix)**:
       - Write minimal code to fix root cause. No scope creep.
       - Re-run same filtered command.
       - **VERIFY PASS**: Confirm test now passes completely.
    3. **REFACTOR Phase**:
       - Clean temporary debug lines, keep test green.
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

## Summary of Associated Subagents

| Subagent                 | Phase   | Role                                              |
| :------------------------ | :------ | :------------------------------------------------- |
| `spec-understand-agent`   | Phase 1 | Reads bug specification file and extracts metadata |
| `spec-coder-agent`        | Phase 2 | Executes TDD RED-GREEN-REFACTOR fix cycle          |

---

## Core Guardrails & Conventions

- **TDD Mandatory**: Never write fix code before watching repro test fail (RED phase).
- **PHPUnit Over Pest**: All PHPUnit tests use PHPUnit test classes (`class BugFixTest extends TestCase`), per project convention in CLAUDE.md.
- **Dusk Isolation**: Never declare DB trait in `tests/Browser/*` — `DatabaseTruncation` inherited from `Tests\DuskTestCase`. `RefreshDatabase` forbidden; `DatabaseMigrations` retired.
- **Dusk Grouping**: UI bug repro goes **into existing lifecycle chain** covering that journey (extra numbered step with own UI + DB assertions) whenever bug lies on that journey. Create new browser method only when repro needs different actor/tenant or is independent negative.
- **Sail Execution**: All execution commands prefixed with `vendor/bin/sail`.
- **Pint Formatting**: Format touched PHP files with `vendor/bin/sail bin pint --dirty --format agent`.
