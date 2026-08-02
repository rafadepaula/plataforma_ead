---
name: laravel-tdd
description: Test-Driven Development specifically for Laravel applications using Pest PHP. Use when implementing any Laravel feature or bugfix - write the test first, watch it fail, write minimal code to pass.
---

# Test-Driven Development for Laravel

## Overview

Write the test first. Watch it fail. Write minimal code to pass.

This skill adapts TDD principles specifically for Laravel applications using Pest PHP, Laravel's testing features, and framework-specific patterns.

## When to Use

**Always for Laravel:**
- New features (controllers, models, services)
- Bug fixes
- API endpoints
- Database migrations and models
- Form validation
- Authorization policies
- Queue jobs
- Artisan commands
- Middleware

**Exceptions (ask your human partner):**
- Throwaway prototypes
- Configuration files
- View-only changes (no logic)

## The Laravel TDD Cycle

```
RED → Verify RED → GREEN → Verify GREEN → REFACTOR → Repeat
```

### RED - Write Failing Test

Write one minimal test showing what the Laravel feature should do.

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

Write simplest Laravel code to pass the test.

### Verify GREEN - Watch It Pass

```bash
php artisan test
```

### REFACTOR - Clean Up Laravel Code

After green only:
- Extract services for complex logic
- Create policies for authorization
- Add query scopes for reusability
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
For UI interactions, JavaScript frontend components, and complete browser workflows:

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
- [ ] Database state verified
- [ ] All tests passing
- [ ] Used RefreshDatabase / DatabaseMigrations
- [ ] Used factories

## Remember

```
Every Laravel feature → Test exists and failed first
Otherwise → Not TDD
```

## Project Note: Plataforma EAD Uses PHPUnit Classes, Not Pest

This repo's `CLAUDE.md` mandates PHPUnit test classes ("If you see a test
using Pest, convert it to PHPUnit") — every example above written as
`test('...', function () { ... })` must be translated to a PHPUnit method
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
(see `laravel/core rules`), and run the narrowest test with
`vendor/bin/sail artisan test --compact --filter=testName` — not the bare
`php artisan test` commands shown elsewhere in this skill (this project runs
everything through Sail). This note is intentionally narrow: the RED→GREEN→
REFACTOR cycle and Laravel-specific patterns above still apply, only the
test syntax and runner prefix differ for this codebase.

## Project Note: Resolve Constructor-Injected Actions From the Container, Never `new X()`

This codebase's single-purpose Action classes (e.g. `SubmitQuizAttemptAction`,
`GradeEssayAnswerAction`) commonly take other Actions/services as
constructor-promoted dependencies rather than being plain zero-arg classes.
A test that instantiates one directly (`new SubmitQuizAttemptAction()`)
will RED with "Too few arguments" the moment a dependency is added to the
constructor — and silently keep working right up until that refactor, so
it's an easy trap to fall into early and only discover much later. Always
resolve Actions under test from the container instead:

```php
$action = app(SubmitQuizAttemptAction::class);
```

This also exercises the real Laravel binding/resolution path (catching a
missing service-container binding as a test failure, not a production
surprise), which a bare `new` never does.
