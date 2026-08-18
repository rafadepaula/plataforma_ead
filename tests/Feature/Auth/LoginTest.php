<?php

namespace Tests\Feature\Auth;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * e-mail+password login (bcrypt) with Spatie role
 * verification, `status=active` gate and rate limiting.
 */
class LoginTest extends TestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entrar');
    }

    public function test_authenticated_admin_is_redirected_away_from_login_screen(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_gestor_is_redirected_away_from_login_screen(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_aluno_is_redirected_away_from_login_screen(): void
    {
        $aluno = User::factory()->aluno()->create();

        $this->actingAs($aluno)->get('/login')->assertRedirect(route('student.courses.index'));
    }

    public function test_authenticated_user_with_intended_url_is_redirected_to_intended_from_login(): void
    {
        $aluno = User::factory()->aluno()->create();

        // Set an intended URL in the session, then visit /login while authenticated.
        // The middleware should redirect to the intended URL (priority over role-based home).
        $this->actingAs($aluno)
            ->withSession(['url.intended' => '/some-protected-page'])
            ->get('/login')
            ->assertRedirect('/some-protected-page');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('student.courses.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_unknown_email(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_is_blocked_from_logging_in(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('correct-password'),
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_role_redirects_to_admin_area(): void
    {
        $admin = User::factory()->create(['org_id' => null, 'password' => bcrypt('correct-password')]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ]);
        }

        RateLimiter::clear('this-key-should-not-match');

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
