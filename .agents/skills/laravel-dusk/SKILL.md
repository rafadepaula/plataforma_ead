---
name: laravel-dusk
description: Laravel Dusk - browser automation and testing API for Laravel apps. Use when write browser tests, automate UI testing, test JavaScript interactions, or implement end-to-end tests in Laravel.
---

# Laravel Dusk Skill

Laravel Dusk browser automation and testing. Expert guidance for expressive browser tests in Laravel apps.

## When to Use This Skill

Trigger when:

- Write or debug browser automation tests for Laravel
- Test user interfaces and JavaScript interactions
- Implement E2E testing workflows
- Set up automated UI testing in Laravel apps
- Work with form submissions, auth flows, page navigation tests
- Configure ChromeDriver or alternative browser drivers
- Use Page Object pattern for test organization
- Test Vue.js components or wait for JavaScript events
- Troubleshoot browser test failures or timing issues

## Project Rule #1 — Group by Lifecycle Chain, Not by Module

**In this repo, unit of organization for Dusk test is the lifecycle chain
(user journey), NOT module, spec, or use case.** One test method drives
entity's whole journey — create, edit, change state, delete, verify
consequence — asserting UI *and* database at every step. Test file belongs
to journey, so it may cross module/spec boundaries when journey does.

Why: every Dusk method pays fixed cost — DB reset, WebDriver session boot,
login, navigation — that dwarfs assertions themselves. Split one lifecycle
into 4 atomic methods, pay that cost 4× and test *fewer* real state
transitions.

```php
// ❌ Old pattern: atomic methods, one module per file.
public function test_gestor_can_create_a_user_via_the_ui(): void {}
public function test_gestor_can_edit_a_user_via_the_ui(): void {}
public function test_gestor_can_deactivate_a_user_via_the_ui(): void {}
public function test_a_deactivated_user_cannot_login(): void {}

// ✅ Current pattern: one chained journey, one login, one DB reset.
public function test_gestor_user_management_full_lifecycle(): void
{
    $this->browse(function (Browser $browser) use ($gestor): void {
        // 1. Criação  → assertSee + assertDatabaseHas
        // 2. Edição   → assertSee + assertDatabaseHas
        // 3. Inativação
        // 4. Consequência: login do usuário inativo é bloqueado
    });
}
```

**Mandatory rules for a chain**

1. Intermediate assertions at **every** step (UI assertion + DB assertion
   whenever step writes). Chain without checkpoints is anti-pattern — see
   `validate-test-quality`.
2. Numbered step comments (`// 1. …`) so failing line maps to step.
3. Naming: `test_{actor}_{entity|journey}_lifecycle` for chains,
   `test_{entity}_validation_rejections` for grouped rejections in one
   form session, `test_{rule}_blocked` / `_isolation` for negatives.
4. **Do not chain what is independent**: multitenant isolation, 403
   authorization negatives, scenarios needing different actor/tenant stay
   in own methods. Chaining them hides failure origin.
5. Chaining happens *inside* one method. No method may rely on state left
   by another (truncation wipes it between tests).
6. **Unit/Feature tests stay atomic** — one behavior per method. Lifecycle
   chaining is browser-E2E-only rule.

**Performance guardrails**

- Target ≤ 1 `loginAs()` per test method. Reuse session with `visit()`
  instead of login again.
- Never `pause()`/`sleep()` as wait — direct wall-clock cost.
- `tests/Browser/*` file with more than ~6 methods signals fragmentation.
  Look for chain to unify.
- Before write new E2E test, look for **existing chain already covering
  that journey and extend it**. New file per module is retired pattern.

Full rule, with canonical example, lives in `testing-conventions`.

## Quick Reference

### 1. Basic Browser Test

```php
public function testBasicExample(): void
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->type('email', 'user@example.com')
            ->type('password', 'password')
            ->press('Login')
            ->assertPathIs('/home');
    });
}
```

### 2. Using Dusk Selectors (Recommended)

```html
<!-- In your Blade template -->
<button dusk="login-button">Login</button>
<input dusk="email-input" name="email" />
```

```php
// In your test - use @ prefix for dusk selectors
$browser->type('@email-input', 'user@example.com')
    ->click('@login-button');
```

### 3. Testing Multiple Browsers

```php
public function testMultiUserInteraction(): void
{
    $this->browse(function (Browser $first, Browser $second) {
        $first->loginAs(User::find(1))
            ->visit('/home');

        $second->loginAs(User::find(2))
            ->visit('/home');
    });
}
```

### 4. Waiting for Elements

```php
// Wait for element to appear
$browser->waitFor('.modal')
    ->assertSee('Confirmation Required');

// Wait for text to appear
$browser->waitForText('Hello World');

// Wait for JavaScript condition
$browser->waitUntil('App.data.servers.length > 0');

// Wait when element is available
$browser->whenAvailable('.modal', function (Browser $modal) {
    $modal->assertSee('Delete Account')
        ->press('OK');
});
```

### 5. Form Interactions

```php
// Text input
$browser->type('email', 'user@example.com')
    ->append('notes', 'Additional text')
    ->clear('description');

// Dropdown selection
$browser->select('size', 'Large')
    ->select('categories', ['Art', 'Music']); // Multiple

// Checkboxes and radio buttons
$browser->check('terms')
    ->radio('gender', 'male');

// File upload
$browser->attach('photo', __DIR__.'/photos/profile.jpg');
```

### 6. Page Object Pattern

```php
// Generate page object
// php artisan dusk:page Login

// app/tests/Browser/Pages/Login.php
class Login extends Page
{
    public function url(): string
    {
        return '/login';
    }

    public function elements(): array
    {
        return [
            '@email' => 'input[name=email]',
            '@password' => 'input[name=password]',
            '@submit' => 'button[type=submit]',
        ];
    }

    public function login(Browser $browser, $email, $password): void
    {
        $browser->type('@email', $email)
            ->type('@password', $password)
            ->press('@submit');
    }
}

// Use in test
$browser->visit(new Login)
    ->login('user@example.com', 'password')
    ->assertPathIs('/dashboard');
```

### 7. Browser Macros (Reusable Methods)

```php
// In AppServiceProvider or DuskServiceProvider
use Laravel\Dusk\Browser;

Browser::macro('scrollToElement', function (string $element) {
    $this->script("$('html, body').animate({
        scrollTop: $('{$element}').offset().top
    }, 0);");

    return $this;
});

// Use in tests
$browser->scrollToElement('#footer')
    ->assertSee('Copyright 2024');
```

### 8. Database Management in Tests

**Project rule: `DatabaseTruncation` is declared once in the base class
`Tests\DuskTestCase`; test classes declare no DB trait at all.**

```php
// tests/DuskTestCase.php — the ONLY place the trait belongs
abstract class DuskTestCase extends BaseTestCase
{
    use DatabaseTruncation;

    // `roles`/`permissions` are seeded by the create_permission_tables
    // MIGRATION (not a seeder), so truncating them would break the whole
    // suite from the 2nd test on with
    // "There is no role named `admin` for guard `web`".
    protected $exceptTables = [
        'migrations',
        'roles',
        'permissions',
        'role_has_permissions',
    ];
}

// tests/Browser/AnyTest.php — no DB trait here
class AnyTest extends DuskTestCase
{
    public function test_some_lifecycle(): void { /* ... */ }
}
```

- `DatabaseMigrations` runs `migrate:fresh` **per test method** (~30
  migrations × N methods) — retired in this repo. Re-adding it to a
  `tests/Browser/*` class silently costs the whole suite minutes; it is
  only acceptable with a written justification in the file (e.g. a test
  that mutates schema at runtime).
- `RefreshDatabase` is **forbidden** in Dusk: its transaction lives in the
  test process' connection and is never visible to the HTTP server process.
- Truncation resets the DB only. Files (`storage/app/public`), cache and
  session survive between methods — chains that upload must use unique
  names or clean up.

Audit command:

```bash
grep -rn "DatabaseMigrations\|RefreshDatabase" tests/Browser/   # expect: empty
```

### 9. JavaScript Execution

```php
// Execute JavaScript
$browser->script('document.documentElement.scrollTop = 0');

// Get JavaScript return value
$path = $browser->script('return window.location.pathname');

// Wait for reload after action
$browser->waitForReload(function (Browser $browser) {
    $browser->press('Submit');
})->assertSee('Success');
```

**Project note (native drag-and-drop):** Selenium/WebDriver does not dispatch
genuine OS-level `dragstart`/`dragover`/`drop` events, so simulating a real
HTML5 drag-and-drop in a Dusk test is unreliable and a common source of
flaky failures in this codebase. When the feature under test exposes its
drop-handling logic as a plain JS function (e.g. `window.ModuleReorder
.persistOrder(list)` in SPEC-05's `ModuleReorder.js`), reorder the DOM nodes
with `$browser->script()` and then invoke that function directly — the same
call path a real `drop` event would trigger — rather than trying to fire
synthetic drag events. See `courses-maintenance`'s "Diagnosing a Dusk
Reorder Test That Times Out" section for the concrete example.

**Project note (`script()` is not chainable):** `Browser::script()` returns
the raw array of per-argument JS return values, not the fluent `Browser`
instance — `$browser->script('...')->assertSee(...)` is a fatal error, not
a no-op. Split it into its own statement:

```php
$browser->script('window.LessonPlayer.reportProgress(1, 540, 600)');
$browser->waitForText('Concluída');
```

(seen in SPEC-07's `tests/Browser/VideoThresholdCompletionTest.php`, which
drives `window.LessonPlayer.reportProgress()` directly for the same
"synthetic event unreliable, call the JS function directly" reason as the
drag-and-drop note above).

### 10. Common Assertions

```php
// Page assertions
$browser->assertPathIs('/dashboard')
    ->assertRouteIs('dashboard')
    ->assertTitle('Dashboard')
    ->assertSee('Welcome Back')
    ->assertDontSee('Error');

// Form assertions
$browser->assertInputValue('email', 'user@example.com')
    ->assertChecked('remember')
    ->assertSelected('role', 'admin')
    ->assertEnabled('submit-button');

// Element assertions
$browser->assertVisible('.success-message')
    ->assertMissing('.error-alert')
    ->assertPresent('button[type=submit]');

// Authentication assertions
$browser->assertAuthenticated()
    ->assertAuthenticatedAs($user);
```

## Key Concepts

### Dusk Selectors vs CSS Selectors

**Dusk selectors** (recommended) use HTML `dusk` attributes that won't change with UI updates:

- More stable than CSS classes or IDs
- Explicitly mark elements for testing
- Use `@` prefix in tests: `$browser->click('@submit-button')`
- Add to HTML: `<button dusk="submit-button">Submit</button>`

**CSS selectors** are more brittle but sometimes necessary:

- `.class-name`, `#id`, `div > button`
- Can break when HTML structure changes
- Use when you don't control the HTML

### `assertSeeIn`/`waitForTextIn` Return CSS-Rendered Text, Not DOM Text

Selenium's `getText()` (which backs `assertSee*`/`waitForText*`) returns the
text as **rendered**, after CSS is applied — not the literal string in the
HTML/DOM. A component styled with `text-transform: uppercase` will make
`assertSeeIn('@status', 'Revogado')` fail/timeout even though the DOM
literally contains `Revogado`, because the browser renders (and `getText()`
returns) `"REVOGADO"`. When a Dusk assertion on text content mysteriously
times out but the page looks right in a screenshot, check the element's
computed `text-transform`/`font-variant` CSS before assuming a
timing/flakiness issue.

**Project note (updated — front_redesign Fase 2):** `<x-ui.badge>` **no
longer** applies `text-transform: uppercase` — sentence case is the only
accepted casing project-wide, and the sole deliberate uppercase exception
left in the system is the `.ds-overline` utility (kicker labels in
`_page-header.scss`/`_drawer.scss`). Do **not** write a new assertion that
hardcodes an all-caps literal (e.g. the old `assertSeeIn('@status',
'REVOGADO')` pattern) expecting a badge to render it — that stopped being
true. Instead use the case-insensitive macros registered in
`tests/DuskTestCase.php` — `assertSeeIgnoringCase`,
`assertDontSeeIgnoringCase`, `assertSeeInIgnoringCase`,
`assertTextEqualsIgnoringCase`, `waitForTextInIgnoringCase` — for any
assertion on text that a component might transform, so the test doesn't
depend on which casing convention is in effect for that component:

```php
$browser->waitForTextInIgnoringCase('@certificate-status-'.$certificate->id, 'Revogado')
    ->assertTextEqualsIgnoringCase('@certificate-status-'.$certificate->id, 'Revogado');
```

(current pattern, `tests/Browser/CertificateRevocationTest.php`). If the
literal casing genuinely matters (e.g. asserting `.ds-overline` is actually
uppercase), assert on an underlying `data-*` attribute/value instead of the
rendered text, or assert the CSS `text-transform` directly.

### Waiting Strategies

**Always wait explicitly** rather than using arbitrary pauses:

- `waitFor('.selector')` - Wait for element to exist
- `waitUntilMissing('.selector')` - Wait for element to disappear
- `waitForText('text')` - Wait for text to appear
- `waitUntil('condition')` - Wait for JavaScript condition
- `whenAvailable('.selector', callback)` - Run callback when available

### Page Objects

Organize complex test logic into **Page classes**:

- Define URL, assertions, and element selectors
- Create reusable methods for page-specific actions
- Improve test readability and maintainability
- Generate with: `php artisan dusk:page PageName`

### Browser Macros

Define **reusable browser methods** for common patterns:

- Register in service provider's `boot()` method
- Use across all tests
- Chain like built-in methods
- Example: scrolling, modal interactions, custom assertions

## Reference Files

This skill includes comprehensive documentation in `references/`:

- **other.md** - Complete Laravel Dusk documentation covering:
    - Installation and configuration
    - ChromeDriver management
    - Test generation and execution
    - Browser interaction methods
    - Form handling and file uploads
    - Waiting strategies and assertions
    - Page Objects and Components patterns
    - CI/CD integration examples

Use the reference file when you need:

- Detailed API documentation for specific methods
- Complete list of available assertions (70+)
- Configuration options for different environments
- Advanced topics like iframes, JavaScript dialogs, or keyboard macros

## Working with This Skill

### For Beginners

1. **Start with basic tests**: Use simple `visit()`, `type()`, `press()`, and `assertSee()` methods
2. **Use Dusk selectors**: Add `dusk` attributes to your HTML for stable selectors
3. **Learn waiting**: Always use `waitFor()` instead of `pause()` for reliable tests
4. **Run tests**: Execute with `php artisan dusk` to see results

### For Intermediate Users

1. **Implement Page Objects**: Organize complex tests with the Page pattern
2. **Database traits**: in this repo the choice is already made — `DatabaseTruncation` in `DuskTestCase`, nothing in the child class
3. **Create browser macros**: Define reusable methods for common workflows
4. **Test authentication**: Use `loginAs()` to bypass login screens
5. **Handle JavaScript**: Use `waitUntil()` for dynamic content and AJAX

### For Advanced Users

1. **Multi-browser testing**: Test real-time features with multiple browsers
2. **Custom waiting logic**: Use `waitUsing()` for complex conditions
3. **Component pattern**: Create reusable components for shared UI elements
4. **CI/CD integration**: Set up Dusk in GitHub Actions, Travis CI, or other platforms
5. **Alternative drivers**: Configure Selenium Grid or other browsers beyond ChromeDriver

### Navigation Tips

- **Quick examples**: Check the Quick Reference section above for common patterns
- **Method documentation**: See `other.md` for complete API reference
- **Assertions list**: Reference file contains all 70+ available assertions
- **Configuration**: Check reference file for environment setup and driver options
- **Best practices**: Look for "Best Practices" section in reference documentation

## Installation & Setup

```bash
# Install Laravel Dusk
composer require laravel/dusk --dev

# Run installation
php artisan dusk:install

# Update ChromeDriver
php artisan dusk:chrome-driver

# Make binaries executable (Unix)
chmod -R 0755 vendor/laravel/dusk/bin/

# Run tests
php artisan dusk
```

## Common Commands

```bash
# Generate new test
php artisan dusk:make LoginTest

# Generate page object
php artisan dusk:page Dashboard

# Generate component
php artisan dusk:component Modal

# Run all tests
php artisan dusk

# Run specific test
php artisan dusk tests/Browser/LoginTest.php

# Run failed tests only
php artisan dusk:fails

# Run with filter
php artisan dusk --group=authentication

# Update ChromeDriver
php artisan dusk:chrome-driver --detect
```

## Resources

### Official Documentation

- Laravel Dusk Documentation: https://laravel.com/docs/12.x/dusk
- API Reference: See `references/other.md` for complete method listings

### Common Patterns in Reference Files

The reference documentation includes:

- 70+ assertion methods with descriptions
- Complete form interaction API
- Waiting strategies and timing best practices
- Page Object pattern examples
- Browser macro definitions
- CI/CD configuration examples
- Environment-specific test setup

## Best Practices

1. **Use Dusk selectors** (`dusk` attributes) instead of CSS classes for stability
2. **Wait explicitly** with `waitFor()` methods instead of arbitrary `pause()`
3. **Organize with Page Objects** for complex test scenarios
4. **Leverage database truncation** for faster test execution (inherited from `DuskTestCase`)
5. **Create browser macros** for frequently repeated actions
6. **Scope selectors** with `with()` or `elsewhere()` for specific page regions
7. **Test user behavior** rather than implementation details
8. **Use authentication shortcuts** like `loginAs()` to skip login flows
9. **Take screenshots** with `screenshot()` for debugging failures
10. **Group by lifecycle chain** (Project Rule #1), not by module — and use `--filter` for targeted execution of a chain

## Troubleshooting

### Common Issues

**ChromeDriver version mismatch:**

```bash
php artisan dusk:chrome-driver --detect
```

**Elements not found:**

- Use `waitFor('.selector')` before interacting
- Check if element is in an iframe
- Verify selector with browser dev tools

**Tests failing randomly:**

- Replace `pause()` with explicit waits
- Increase timeout: `waitFor('.selector', 10)`
- Use `waitUntil()` for JavaScript conditions

**Database state issues:**

- Use `DatabaseTruncation` trait
- Reset data in `setUp()` method
- Check for transactions in application code

**New JS module added but its behavior doesn't run in the browser (project-specific, hit in SPEC-15):**

- Dusk drives the real compiled assets in `public/build`, not a live Vite
  dev server — a newly created/edited `resources/js/modules/*.js` (and its
  `resources/js/app.js` import) is invisible to Dusk until
  `vendor/bin/sail npm run build` is re-run. Symptom: the feature works
  when clicked manually in a browser with `npm run dev` running, but a
  Dusk test against the same click silently no-ops (e.g. a modal never
  opens). Rebuild assets before re-running a failing Dusk test that
  exercises new/changed JS.

**Two `artisan dusk` runs at once corrupt each other (project-specific, hit
in `spec/front_redesign` Fase 3 parallel bucket agents):**

- All `tests/Browser/*` share one MySQL `testing` schema via the single
  `DatabaseTruncation` trait on `DuskTestCase` (see §8) — there is no
  per-run/per-worker schema isolation. Two `vendor/bin/sail artisan dusk`
  invocations running at the same time (e.g. sibling agents each verifying
  their own bucket of a multi-agent task against the same container)
  truncate/seed against each other mid-run, surfacing as flaky, contextless
  errors: alternating "table already exists" / "table doesn't exist" /
  "unknown column" on tables neither test touches. This is not a defect in
  either bucket's markup or migrations. Never run the Dusk suite from more
  than one agent/process against the same container concurrently; serialize
  Dusk runs (last bucket runs it, or the orchestrator runs it once after
  all buckets land) instead of each bucket verifying its own slice live.
  If the schema is left corrupted from an interrupted concurrent run,
  `DROP DATABASE testing; CREATE DATABASE testing;` before the next attempt.

## Never Leave a Vite Dev Server Running While Dusk Runs (Project-Specific)

`npm run dev`/`composer run dev` write `public/hot`. While that file
exists Laravel serves every asset from `http://localhost:5173`, and the
Selenium container resolves `localhost` as **itself** — so every CSS and
JS request dies with `ERR_CONNECTION_REFUSED` and the whole suite runs
against unstyled, script-less pages. There is no obvious error: tests
just fail on missing elements or dead `data-bs-*` behaviour.

- Before any Dusk run: `ls public/hot` must fail, and no `vite` process
  may be alive.
- Build assets with `vendor/bin/sail npm run build` only. Never suggest
  `npm run dev` as a fix for a stale bundle when Dusk is the verifier.

## Chrome's Password Manager Bubble Eats Keystrokes (Project-Specific)

Dozens of password-form submissions in one Chrome profile make the "Salvar
senha?" bubble overlay the page and swallow input in *later* tests, which
then fail far from the real cause. `tests/DuskTestCase.php` already
disables it through Chrome `prefs`
(`credentials_enable_service`, `profile.password_manager_enabled`,
`profile.password_manager_leak_detection` all `false`). Keep those
`prefs` when editing driver options — dropping them reintroduces
flakiness that looks like a selector problem.

## Responsive Coverage Lives in `tests/Browser/Theme/`

Mobile and accessibility guardrails are browser tests, not Feature tests:
`ResponsiveNoHorizontalScrollTest` (compares `document.body.scrollWidth`
with `window.innerWidth` at 320/375/768/1024/1440),
`StudentMobileScreensTest` (`resize(375, 812)` over the four Aluno
screens), `KeyboardNavigationTest`, `AuditDiffModalHighlightTest`.
Use `$browser->resize(w, h)` before `visit()`, and assert layout facts
through `script()` rather than screenshots.

## Notes

- Laravel Dusk uses ChromeDriver by default (no Selenium/JDK required)
- Supports alternative browsers via Selenium WebDriver protocol
- Tests are stored in `tests/Browser` directory
- Page objects go in `tests/Browser/Pages`
- Screenshots saved to `tests/Browser/screenshots` on failure
- Console logs saved to `tests/Browser/console` for debugging
