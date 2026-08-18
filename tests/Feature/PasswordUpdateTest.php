<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * self-service password change, gated by the
 * native `current_password` rule and rate-limited via `throttle:6,1`.
 */
class PasswordUpdateTest extends TestCase
{
    public function test_user_can_update_their_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        $response = $this->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success', 'Senha alterada com sucesso.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_wrong_current_password_fails_validation_and_password_is_unchanged(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        $response = $this->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    /**
     * changing the password on one device must log out every
     * OTHER active session for the same user. This exercises the real
     * `auth.session` (`AuthenticateSession`) middleware registered on the
     * `web` group in `bootstrap/app.php`, rather than `actingAs()`, since
     * that middleware is what actually enforces the invalidation.
     */
    public function test_changing_password_logs_out_other_active_sessions(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'status' => 'active',
        ]);

        $sessionCookie = config('session.cookie');

        // Device A logs in and keeps its session cookie.
        $deviceA = $this->post('/login', [
            'email' => $user->email,
            'password' => 'old-password',
        ]);
        $deviceASession = $this->sessionCookieValueFrom($deviceA, $sessionCookie);

        // Device B logs in independently and keeps its own session cookie.
        $deviceB = $this->post('/login', [
            'email' => $user->email,
            'password' => 'old-password',
        ]);
        $deviceBSession = $this->sessionCookieValueFrom($deviceB, $sessionCookie);

        // Device B is authenticated before the password change.
        $this->withUnencryptedCookie($sessionCookie, $deviceBSession)
            ->get('/profile')
            ->assertOk();

        // Device A changes the password.
        $this->withUnencryptedCookie($sessionCookie, $deviceASession)
            ->put('/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.edit'));

        // Device B's now-stale session gets logged out on its next request.
        $this->withUnencryptedCookie($sessionCookie, $deviceBSession)
            ->get('/profile')
            ->assertRedirect(route('login'));
    }

    /**
     * `throttle:6,1` on `password.update` stops `current_password`
     * from being a brute-force oracle against the active session's password.
     */
    public function test_password_update_is_rate_limited_after_six_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasErrors('current_password');
        }

        $this->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(429);
    }

    private function sessionCookieValueFrom(TestResponse $response, string $cookieName): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        $this->fail("No {$cookieName} cookie was set on the response.");
    }
}
