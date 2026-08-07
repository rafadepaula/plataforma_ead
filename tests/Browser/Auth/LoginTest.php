<?php

namespace Tests\Browser\Auth;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Password;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-04 RF01 — E2E coverage of the login screen (SPEC-00 §5 mandates
 * Dusk coverage of every screen). Uses DatabaseMigrations (not
 * RefreshDatabase) since Dusk drives the browser and the app server as
 * separate HTTP processes/connections.
 */
class LoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_login_with_the_form(): void
    {
        $user = User::factory()->create([
            'email' => 'aluno@example.com',
            'password' => bcrypt('correct-password'),
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->visit('/login')
                ->assertPresent('@login-form')
                ->type('@login-email', 'aluno@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticatedAs($user);
        });
    }

    public function test_wrong_password_shows_a_validation_error(): void
    {
        User::factory()->create([
            'email' => 'aluno@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/login')
                ->type('@login-email', 'aluno@example.com')
                ->type('@login-password', 'totally-wrong-password')
                ->press('@login-submit')
                ->waitForText('These credentials do not match our records.')
                ->assertGuest();
        });
    }

    public function test_user_can_logout(): void
    {
        // The logout control lives in the app shell's topbar (see
        // `resources/views/components/layout/topbar.blade.php`), which is
        // only rendered by pages extending `layouts.app` (e.g. Organization
        // CRUD, admin-only). The root `/` route still serves the framework's
        // default `welcome` view (no topbar), so an authenticated admin
        // visits an actual app screen here rather than `/`.
        $user = User::factory()->create();
        $user->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/organizations')
                ->assertAuthenticatedAs($user)
                ->waitFor('form[action$="/logout"] button')
                ->click('form[action$="/logout"] button')
                ->waitForLocation('/')
                ->assertGuest();
        });
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'aluno@example.com',
            'password' => bcrypt('correct-password'),
            'status' => 'inactive',
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/login')
                ->type('@login-email', 'aluno@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForText('These credentials do not match our records.')
                ->assertGuest();
        });
    }

    public function test_user_can_reset_the_password_through_the_forgot_password_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('old-password'),
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/forgot-password')
                ->assertPresent('@forgot-password-form')
                ->type('@forgot-password-email', 'reset@example.com')
                ->press('@forgot-password-submit')
                ->waitForText('We have emailed your password reset link.');
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $this->browse(function (Browser $browser) use ($user, $token): void {
            $browser->visit(route('password.reset', $token))
                ->assertPresent('@reset-password-form')
                ->type('@reset-password-email', 'reset@example.com')
                ->type('@reset-password-password', 'new-password-123')
                ->type('@reset-password-password-confirmation', 'new-password-123')
                ->press('@reset-password-submit')
                ->waitForLocation('/login')
                ->waitForText('Your password has been reset.')
                ->type('@login-email', 'reset@example.com')
                ->type('@login-password', 'new-password-123')
                ->press('@login-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticatedAs($user);
        });
    }
}
