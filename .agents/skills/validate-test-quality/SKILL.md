---
name: validate-test-quality
description: >-
  Use when reviewing unit or feature tests, auditing test suites for fake assertions,
  checking whether code coverage is meaningful, or verifying that tests validate real logic.
---

# Validate Test Quality

## Overview

High code coverage can hide useless, tautological, or fake tests that pass without verifying business logic. Real test validation checks whether assertions verify genuine domain invariants, state changes, and error conditions, or if the test merely executes code for coverage metrics.

## When to Use

- When reviewing new or existing unit/feature tests for quality and efficacy.
- When auditing code coverage to detect "fake assertions" or "coverage padding".
- When verifying if a test will catch regressions when production code breaks.
- Before accepting PRs or merging code that adds new tests.

---

## The 6 Pillars of Real Test Validation

When auditing any test, evaluate it against these 6 pillars:

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
- ❌ **Anti-Pattern**: Mocking the class/method being tested, setting return values on the mock, and calling the mock.
- 🐛 **Why It Fails**: 0% of production code executes. The test validates PHPUnit/Mockery, not application logic.
- ✅ **Fix**: Instantiate concrete class. Mock external dependencies (APIs, repositories), not the SUT.

### 2. Tautological / Self-Referential Assertions
- ❌ **Anti-Pattern**: Assertions true by logical necessity or repeating exact SUT formulas (`$this->assertTrue(true)`, `$this->assertEquals($val, $val)`, `assert(add(2, 3) === 2 + 3)`).
- 🐛 **Why It Fails**: Passes regardless of whether production code is correct or broken.
- ✅ **Fix**: Assert against expected hardcoded domain outcomes or verified contract schema.

### 3. Weak / Redundant Factory Assertions
- ❌ **Anti-Pattern**: Asserting `$this->assertNotNull($instance)` or `$this->assertInstanceOf(User::class, $user)` immediately after calling `User::factory()->create()`.
- 🐛 **Why It Fails**: Factories always return model instances or throw exceptions. Does not verify database persistence or business attributes.
- ✅ **Fix**: Replace with `$this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $email])`.

### 4. Coverage Padding (Assertion-Free Execution)
- ❌ **Anti-Pattern**: Executing complex controller or service methods without assertions, or wrapping execution in `try { ... } catch (\Exception $e) {}` with no assertions.
- 🐛 **Why It Fails**: Increases coverage line count while providing zero safety.
- ✅ **Fix**: Assert HTTP response status (`$response->assertOk()`), JSON structure, and database side effects.

### 5. Happy-Path-Only Illusion (Missing Failure Paths)
- ❌ **Anti-Pattern**: Test suite has 10 tests for successful operations, but zero tests for invalid validation inputs (422), unauthorized roles (403), or cross-tenant data access.
- 🐛 **Why It Fails**: Bugs occur in edge cases and failure paths, not in ideal happy paths.
- ✅ **Fix**: Require test coverage for authorization policy rejection, validation failure responses, and missing tenant context (`UnresolvedOrgContextException`).

### 6. Fragile / Over-Specified Mocking
- ❌ **Anti-Pattern**: Asserting private internal method execution order (`$mock->expects($this->once())->method('internalHelper')`).
- 🐛 **Why It Fails**: Breaks during safe code refactoring even when external behavior is preserved.
- ✅ **Fix**: Test public API behavior, state changes, and return values.

---

## 🚫 Mandatory Fail-Path & Negative-Path Testing

A test suite that only verifies happy-paths provides **false confidence**. Real code quality and resilience are defined by how gracefully system components handle invalid inputs, unauthorized access, boundary violations, and exception triggers.

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

#### ❌ Incomplete: Testing Happy-Path Only
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

#### ✅ Complete: Testing Happy-Path AND Every Fail-Path
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


## Validation Workflow

### Step 1: Read Test & Identify System Under Test (SUT)
Locate the actual class or route being tested. Check if concrete SUT code is invoked or if it is mocked.

### Step 2: Mental Mutation Analysis ("The Devil's Advocate")
Ask: *"If I invert an `if` condition, comment out a scope filter, or change a return value in the production code, will this test fail?"*
- If the test still passes after production code is mutated -> **Fake / Blind Test**.

### Step 3: Audit Assertions & State Checks
Ensure assertions verify:
1. **Output Value**: Exact returned response, JSON payload, or status code.
2. **Database Persistence**: `assertDatabaseHas` / `assertDatabaseMissing` for writes/deletes.
3. **Tenant / Security Boundaries**: Cross-tenant isolation (Org A cannot see Org B records).

---

## Test Audit Report Format

When reviewing tests, produce a structured evaluation report using this format:

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
