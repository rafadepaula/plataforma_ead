---
name: laravel-tdd
description: TDD for Laravel apps with Pest PHP. Use when implement any Laravel feature or bugfix - write test first, watch it fail, write minimal code to pass.
---

# Test-Driven Development for Laravel

## Overview

Write test first. Watch it fail. Write minimal code to pass.

Skill adapts TDD to Laravel apps: Pest PHP, Laravel testing features, framework patterns.

## When to Use

**Always for Laravel:**
- New features (controllers, models, services)
- Bug fixes
- API endpoints
- DB migrations and models
- Form validation
- Authorization policies
- Queue jobs
- Artisan commands
- Middleware

**Exceptions (ask human partner):**
- Throwaway prototypes
- Config files
- View-only changes (no logic)

## The Laravel TDD Cycle

```
RED → Verify RED → GREEN → Verify GREEN → REFACTOR → Repeat
```

### RED - Write Failing Test

Write one minimal test showing what Laravel feature should do.

**Feature Test Example:**
```php
<?php

use App\Models\User;
use App\Models\Post;

test('authenticated user can create post', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/posts', [
            'title' => 'My First Post',
            'content' => 'Post content here',
        ])
        ->assertRedirect('/posts');
    
    expect(Post::where('title', 'My First Post')->exists())->toBeTrue();
    expect(Post::first()->user_id)->toBe($user->id);
});
```

### Verify RED - Watch It Fail

```bash
php artisan test --filter=authenticated_user_can_create_post
```

### GREEN - Minimal Laravel Code

Write simplest Laravel code to pass test.

### Verify GREEN - Watch It Pass

```bash
php artisan test
```

### REFACTOR - Clean Up Laravel Code

After green only:
- Extract services for complex logic
- Create policies for authorization
- Add query scopes for reuse
- Use events for side effects

## Laravel-Specific Test Patterns

### Database Testing
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates post in database', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/posts', ['title' => 'Test', 'content' => 'Content']);
    
    $this->assertDatabaseHas('posts', ['title' => 'Test']);
});
```

### Authorization Testing
```php
test('user cannot delete others posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    
    $this->actingAs($user)
        ->delete("/posts/{$post->id}")
        ->assertForbidden();
});
```

### API Testing
```php
test('creates post via API', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', ['title' => 'API Post', 'content' => 'Content'])
        ->assertCreated();
});
```

### End-to-End (E2E) Browser Testing with Laravel Dusk
For UI interactions, JavaScript frontend components, full browser workflows.

**Project rule — TDD granularity differs by suite:**

- **Unit/Feature: atomic.** One behavior per test method, RED-GREEN per behavior.
- **Dusk/E2E: lifecycle chain.** RED step is **new numbered step appended to chain** covering that journey (create → edit → state change → delete → consequence), with own UI + DB assertions — not new atomic method, not new file per module. Only independent negatives (403, cross-tenant, other actor) get own method. Why: each browser method pays DB reset + WebDriver boot + login + navigation. See `testing-conventions` / `laravel-dusk`.
- Dusk classes declare **no** DB trait. `DatabaseTruncation` inherited from `Tests\DuskTestCase`. `RefreshDatabase` forbidden there.

```php
use Laravel\Dusk\Browser;

test('user can log in and view dashboard', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('Log In')
            ->assertPathIs('/dashboard')
            ->assertSee('Welcome');
    });
});
```

**Running Dusk Tests:**
```bash
# Local command
php artisan dusk

# Sail command (Containerized)
./vendor/bin/sail artisan dusk
./vendor/bin/sail artisan dusk --filter=user_can_log_in_and_view_dashboard
```


## Verification Checklist

- [ ] Migration test passes
- [ ] Model relationships tested
- [ ] Controller & API integration tests pass (`php artisan test`)
- [ ] E2E Dusk browser tests pass for UI changes (`php artisan dusk`)
- [ ] Validation rules tested
- [ ] Authorization tested
- [ ] DB state verified
- [ ] All tests passing
- [ ] Used `RefreshDatabase` in Unit/Feature; Dusk classes declare no DB trait (`DatabaseTruncation` inherited from `Tests\DuskTestCase`)
- [ ] New browser coverage added as lifecycle-chain step, not new atomic method
- [ ] Used factories

## Remember

```
Every Laravel feature → Test exists and failed first
Otherwise → Not TDD
```

## Project Note: Plataforma EAD Uses PHPUnit Classes, Not Pest

Repo `CLAUDE.md` mandates PHPUnit test classes ("If you see a test using Pest,
convert it to PHPUnit"). Every example above written as
`test('...', function () { ... })` must be translated to PHPUnit method
before use here, e.g.:

```php
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/posts', ['title' => 'My First Post', 'content' => 'Post content here'])
            ->assertRedirect('/posts');

        $this->assertDatabaseHas('posts', ['title' => 'My First Post', 'user_id' => $user->id]);
    }
}
```

Generate new tests with `vendor/bin/sail artisan make:test --phpunit {Name}`
(see `laravel/core rules`). Run narrowest test with
`vendor/bin/sail artisan test --compact --filter=testName` — not bare
`php artisan test` commands shown elsewhere in this skill. This project runs
everything through Sail. Note is narrow on purpose: RED→GREEN→REFACTOR cycle
and Laravel patterns above still apply. Only test syntax and runner prefix
differ for this codebase.

Data-driven tests use the PHPUnit 12 **attribute** form, not the legacy
docblock — this project's PHPUnit config does not recognize `@dataProvider`:

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('rolesProvider')]
public function test_admin_can_set_any_role(string $role): void { ... }

public static function rolesProvider(): array
{
    return [['admin'], ['gestor'], ['aluno']];
}
```

(precedent: `tests/Unit/Rules/CpfTest.php`). A docblock `@dataProvider` silently
runs the test zero times instead of failing loudly — verify a new
data-provider test's assertion count/output before trusting a green run.

## Project Note: Resolve Constructor-Injected Actions From the Container, Never `new X()`

This codebase single-purpose Action classes (e.g. `SubmitQuizAttemptAction`,
`GradeEssayAnswerAction`) often take other Actions/services as
constructor-promoted dependencies, not plain zero-arg classes.
Test that instantiates one directly (`new SubmitQuizAttemptAction()`)
REDs with "Too few arguments" moment dependency added to constructor — and
silently keeps working right up until that refactor. Easy trap to fall into
early, discovered much later. Always resolve Actions under test from container:

```php
$action = app(SubmitQuizAttemptAction::class);
```

Also exercises real Laravel binding/resolution path, catching missing
service-container binding as test failure, not production surprise. Bare `new`
never does.

## Project Note: Testing a "Mail Failure Must Not Roll Back the Transaction" Boundary

Several modules (e.g. the notifications module) wrap `->notify()`/
`Notification::send()` call site in `try/catch (Throwable) { Log::error(...) }`
so mail transport failure never rolls back DB write already committed, never
500s request. `Mail::fake()`/`Notification::fake()` cannot exercise this branch
— they swallow call instead of throwing. Use `Notification` facade own mock
expectations to force failure. Assert `Log::error()` reached, not bubbled
exception:

```php
Log::shouldReceive('error')->once();
Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP indisponível'));

// ...perform the action that triggers the notification...

// then assert the triggering row still exists/committed, e.g.:
$this->assertDatabaseHas('course_user', [...]);
```

See `tests/Feature/NotificationTriggersTest.php` for full pattern
(includes per-recipient variant, where `Notification::shouldReceive('send')`
asserted without `->once()` since called once per recipient in loop).

## Project Note: A Fully-Mocked `Log` Facade Breaks When New Code Adds a `Log::channel(...)` Call

Some existing tests (e.g. two in `tests/Feature/NotificationTriggersTest.php`)
fully mock `Log` facade (`Log::shouldReceive(...)`, no fallback) to assert on
specific log call. `AuditService::log()` unconditionally calls
`Log::channel('audit')->info(...)` on every `AuditLog`-observed model mutation
and every explicit audit call site. So any pre-existing test that fully mocks
`Log` then exercises code path now also triggering audit write (e.g.
`IssueCertificateAction` via `AuditableTrait` on `Certificate`) fails with
unexpected-call error — not because test assertion wrong, but because new
module started using same facade. When adding new `Log::channel(...)` call site
to already-audited code, grep existing tests for `Log::shouldReceive`/
`Log::spy` and add matching
`Log::shouldReceive('channel')->with('audit')->andReturnSelf()`
(or equivalent) expectation. Do not assume new call invisible to old mocks.
