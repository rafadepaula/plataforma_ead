---
name: validate-test-quality
description: >-
  Use when review unit or feature tests, audit test suites for fake assertions,
  check whether code coverage meaningful, or verify tests validate real logic.
---

# Validate Test Quality

## Overview

High code coverage hide useless, tautological, fake tests that pass without verifying business logic. Real test validation check whether assertions verify genuine domain invariants, state changes, error conditions — or test merely execute code for coverage metrics.

## When to Use

- Review new or existing unit/feature tests for quality and efficacy.
- Audit code coverage to detect "fake assertions" or "coverage padding".
- Verify test catch regressions when production code breaks.
- Before accept PRs or merge code adding new tests.

---

## The 6 Pillars of Real Test Validation

Audit any test against these 6 pillars:

```
┌──────────────────────────────────────────────────────────┐
│ 1. SUT Integrity: Executing real code (not mocking SUT)  │
├──────────────────────────────────────────────────────────┤
│ 2. Assertion Meaningfulness: Asserting domain invariants  │
├──────────────────────────────────────────────────────────┤
│ 3. Mutation Resiliency: Fails if prod code breaks        │
├──────────────────────────────────────────────────────────┤
│ 4. State Verification: Database, response, & side-effects│
├──────────────────────────────────────────────────────────┤
│ 5. Failure Paths: Testing 403, 422, exceptions, & bounds │
├──────────────────────────────────────────────────────────┤
│ 6. Refactor Resilience: Testing outcome, not internals   │
└──────────────────────────────────────────────────────────┘
```

---

## Key Test Smells & Fake Assertion Patterns

### 1. Mocking the System Under Test (Self-Mocking)
- **Anti-Pattern**: Mock class/method being tested, set return values on mock, call mock.
- **Why It Fails**: 0% of production code executes. Test validates PHPUnit/Mockery, not application logic.
- **Fix**: Instantiate concrete class. Mock external dependencies (APIs, repositories), not SUT.

### 2. Tautological / Self-Referential Assertions
- **Anti-Pattern**: Assertions true by logical necessity, or repeating exact SUT formulas (`$this->assertTrue(true)`, `$this->assertEquals($val, $val)`, `assert(add(2, 3) === 2 + 3)`).
- **Why It Fails**: Passes regardless of production code correct or broken.
- **Fix**: Assert against expected hardcoded domain outcomes or verified contract schema.

### 3. Weak / Redundant Factory Assertions
- **Anti-Pattern**: Assert `$this->assertNotNull($instance)` or `$this->assertInstanceOf(User::class, $user)` right after `User::factory()->create()`.
- **Why It Fails**: Factories always return model instances or throw exceptions. No proof of DB persistence or business attributes.
- **Fix**: Replace with `$this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $email])`.

### 4. Coverage Padding (Assertion-Free Execution)
- **Anti-Pattern**: Execute complex controller or service methods with no assertions, or wrap execution in `try { ... } catch (\Exception $e) {}` with no assertions.
- **Why It Fails**: Grows coverage line count, gives zero safety.
- **Fix**: Assert HTTP response status (`$response->assertOk()`), JSON structure, DB side effects.

### 5. Happy-Path-Only Illusion (Missing Failure Paths)
- **Anti-Pattern**: Suite has 10 tests for successful operations, zero for invalid validation inputs (422), unauthorized roles (403), cross-tenant data access.
- **Why It Fails**: Bugs live in edge cases and failure paths, not ideal happy paths.
- **Fix**: Require coverage for authorization policy rejection, validation failure responses, missing tenant context (`UnresolvedOrgContextException`).

### 6. Fragile / Over-Specified Mocking
- **Anti-Pattern**: Assert private internal method execution order (`$mock->expects($this->once())->method('internalHelper')`).
- **Why It Fails**: Breaks on safe refactor even when external behavior preserved.
- **Fix**: Test public API behavior, state changes, return values.

---

## Mandatory Fail-Path & Negative-Path Testing

Suite testing only happy-paths gives **false confidence**. Real quality and resilience defined by how system handles invalid inputs, unauthorized access, boundary violations, exception triggers.

**Rule: Every feature, API endpoint, and domain service MUST have explicit tests tracking its fail-paths.**

### The 6 Mandatory Fail-Path Categories

Every feature test audit must verify coverage across these 6 negative scenarios:

| Fail-Path Category | Expected Response / Behavior | What Must Be Tested |
| :--- | :--- | :--- |
| **1. Form Validation Failures** | `HTTP 422 Unprocessable Entity` | Missing required fields, invalid data formats (email, CPF, UUID), string lengths, invalid types, failed custom rules. |
| **2. Authorization Rejections** | `HTTP 403 Forbidden` / `HTTP 401` | Unauthenticated users, unauthorized roles (`aluno` accessing `gestor` route), missing policy permissions. |
| **3. Cross-Tenant Data Leaks** | `HTTP 403` / `HTTP 404` | User from Tenant A attempting to read, update, or delete records owned by Tenant B. |
| **4. Resource Not Found** | `HTTP 404 Not Found` | Non-existent IDs, soft-deleted records, or child resources not belonging to the parent (`Lesson` ID belonging to another `Course`). |
| **5. Domain Exceptions & Guards** | Custom Exceptions / HTTP Error | `UnresolvedOrgContextException`, expired tokens, inactive user accounts, out-of-quota actions. |
| **6. Duplicate & Conflict State** | `HTTP 409 Conflict` / Database Exception | Duplicate unique column insertion (e.g. existing email/CPF), state machine transition violations (e.g. editing a completed quiz). |

### Comparison: Happy-Path Only vs Full Fail-Path Coverage

#### Incomplete: Testing Happy-Path Only
```php
// ❌ INCOMPLETE: Only tests successful creation.
test('gestor can create course', function () {
    $gestor = User::factory()->gestor()->create();

    $response = $this->actingAs($gestor)->postJson('/api/courses', [
        'title' => 'New Course',
        'workload_hours' => 40,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('courses', ['title' => 'New Course']);
});
```

#### Complete: Testing Happy-Path AND Every Fail-Path
```php
// ✅ 1. Happy-Path
test('gestor can create course in their organization', function () { ... });

// ✅ 2. Fail-Path: Unauthenticated user rejected
test('unauthenticated user cannot create course', function () {
    $this->postJson('/api/courses', ['title' => 'Course'])
        ->assertUnauthorized();
});

// ✅ 3. Fail-Path: Unauthorized role rejected (Aluno)
test('aluno cannot create course', function () {
    $aluno = User::factory()->aluno()->create();

    $this->actingAs($aluno)->postJson('/api/courses', ['title' => 'Course'])
        ->assertForbidden();
});

// ✅ 4. Fail-Path: Validation error on missing required fields
test('course creation fails validation when title is missing', function () {
    $gestor = User::factory()->gestor()->create();

    $response = $this->actingAs($gestor)->postJson('/api/courses', [
        'workload_hours' => 40,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

// ✅ 5. Fail-Path: Cross-tenant payload override attempt fails
test('gestor cannot force course creation into another organization', function () {
    $otherOrg = Organization::factory()->create();
    $gestor = User::factory()->gestor()->create(); // Belongs to org A

    $response = $this->actingAs($gestor)->postJson('/api/courses', [
        'title' => 'Hacked Course',
        'org_id' => $otherOrg->id, // Attacker trying to spoof org_id
    ]);

    $response->assertCreated();
    // Verify record was created under Gestor's org, NOT the spoofed org
    $this->assertDatabaseHas('courses', [
        'title' => 'Hacked Course',
        'org_id' => $gestor->org_id,
    ]);
});
```

---

## Auditing Lifecycle-Chained E2E Tests (Dusk)

Browser tests in `tests/Browser/` grouped by **lifecycle chain** (one method drives create → edit → state change → delete → consequence), not by atomic scenario. **Chained E2E test is valid and preferred — do NOT flag it as "test doing too much"** — provided it satisfies checkpoint rule below. Rationale is cost: each Dusk method pays DB reset, WebDriver boot, login, navigation, so fragmenting a lifecycle multiplies wall-clock without adding coverage.

### Checkpoint Rule (what makes a chain genuine)

| Requirement | Verdict if missing |
| :--- | :--- |
| UI assertion after **every** step (`assertSee`/`assertSeeIn`/`waitForText`) | 🔴 Blind chain — a broken middle step is invisible until a later step happens to fail |
| DB assertion (`assertDatabaseHas`/`assertDatabaseMissing`) after every step that **writes** | 🔴 Coverage padding — the chain executes lines without proving persistence |
| Numbered step comments mapping line → step | 🟡 Accepted but hard to diagnose; request them |
| Final-state assertion after `browse()` closes | 🟡 Recommended for the terminal state of the entity |
| Independent negatives (403, cross-tenant, other actor) kept in **separate** methods | 🔴 If chained: failure origin is masked and the negative may never run because an earlier step failed |

### New E2E-Specific Smells

#### 7. Blind Chain (chained steps with no intermediate assertions)
- **Anti-Pattern**: long `$browser->visit()->type()->press()->visit()->type()->press()` ribbon asserting only at very end.
- **Why It Fails**: step that silently no-ops caught only if it happens to break later step; failure line points at wrong step.
- **Fix**: assert UI + DB after each step, with numbered comments.

#### 8. Atomic E2E Fragmentation (the inverse smell)
- **Anti-Pattern**: four browser methods, each re-seeds, re-logins, re-navigates to perform one action of *same* entity lifecycle.
- **Why It Fails**: pays fixed browser cost N times, never exercises real state transitions between steps (e.g. "deactivated user can no longer log in").
- **Fix**: merge into one lifecycle chain, keeping every assertion from original methods.

#### 9. Lost Assertions During Consolidation
- **Anti-Pattern**: merge 4 atomic tests into chain, drop `assertDatabaseMissing`, multitenant negative, or validation-rejection assertion "because flow already covers it".
- **Why It Fails**: consolidation reducing assertion count is silent coverage regression, invisible in line-coverage metrics.
- **Fix**: when auditing consolidation diff, count assertions **before vs after** — chained method must contain union of originals' assertions.

### Coverage Mapping Rule

Use Case / spec scenario counts as covered when its assertions exist **somewhere in a chain**, even if that chain lives in a file named after a different module. Never require one test method (or one file) per use case or per module — require one *assertion set* per scenario.

---


## Validation Workflow

### Step 1: Read Test & Identify System Under Test (SUT)
Locate actual class or route being tested. Check whether concrete SUT code invoked or mocked.

### Step 2: Mental Mutation Analysis ("The Devil's Advocate")
Ask: *"If I invert an `if` condition, comment out a scope filter, or change a return value in the production code, will this test fail?"*
- If test still passes after production code mutated -> **Fake / Blind Test**.

### Step 3: Audit Assertions & State Checks
Ensure assertions verify:
1. **Output Value**: Exact returned response, JSON payload, or status code.
2. **Database Persistence**: `assertDatabaseHas` / `assertDatabaseMissing` for writes/deletes.
3. **Tenant / Security Boundaries**: Cross-tenant isolation (Org A cannot see Org B records).

---

## Test Audit Report Format

When reviewing tests, produce structured evaluation report in this format:

```markdown
### 🧪 Test Quality Audit Report

#### File: `tests/Feature/ExampleTest.php`

| Test Method | Quality Verdict | Identified Smells | Mutation Safety |
| :--- | :--- | :--- | :--- |
| `test_user_created` | 🔴 Fake / Useless | Tautological (`assertTrue(true)`), Weak factory check | ❌ Passes if DB write fails |
| `test_price_calculator` | 🔴 Fake / Self-Mock | Mocked SUT, Zero prod code execution | ❌ Passes if calculation broken |
| `test_tenant_isolation` | 🟢 Genuine / High Value | None (Asserts cross-tenant 403 & DB scope) | ✅ Fails if `OrgScope` removed |

#### Detailed Findings & Refactoring Recommendations

##### 1. `test_user_created`
- **Issue**: Uses `$this->assertTrue(true)` and `$this->assertNotNull($user)` on factory object.
- **Refactored Code**:
```php
public function test_user_can_be_registered_and_persisted(): void
{
    $response = $this->post(route('register'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
}
```
```

---

## Rationalizations & Red Flags

| Rationalization | Why It Is Wrong | Mandatory Rule |
| :--- | :--- | :--- |
| *"Code coverage is 95%, so the tests are good."* | Line coverage measures execution, not assertion efficacy. A test can execute lines with zero assertions. | **Always inspect assertion quality, not just line coverage percentages.** |
| *"Testing happy path is enough for unit tests."* | 80%+ of bugs occur in edge cases, authorization failures, and invalid inputs. | **Require failure paths (403, 422, exceptions) in test validation.** |
| *"Mocking the SUT makes the test faster."* | Mocking the SUT tests the mock library, executing 0 lines of production code. | **Never mock the SUT. Only mock external dependencies.** |
| *"`assertNotNull($instance)` proves it worked."* | Factory methods always return an object unless an exception is thrown. | **Assert specific database attributes, JSON outputs, or state changes.** |
