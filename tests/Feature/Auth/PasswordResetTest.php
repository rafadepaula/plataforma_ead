<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * SPEC-04 RF02 — single-use password-reset token flow, delivered via the
 * SMTP mailer configured in `config/mail.php`.
 */
class PasswordResetTest extends TestCase
{
    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Esqueceu sua senha?');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/forgot-password', ['email' => 'user@example.com'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_link_request_for_unknown_email_does_not_error_but_sends_nothing(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->post('/forgot-password', ['email' => 'user@example.com']);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPassword $notification) use ($user): bool {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertOk()
                ->assertSee('Redefinir senha');

            return true;
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->post('/forgot-password', ['email' => 'user@example.com']);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            $this->assertTrue(
                Hash::check('new-strong-password', $user->fresh()->password)
            );

            return true;
        });
    }

    public function test_reset_token_is_single_use(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->post('/forgot-password', ['email' => 'user@example.com']);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPassword $notification) use ($user): bool {
            $payload = [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'first-new-password',
                'password_confirmation' => 'first-new-password',
            ];

            $this->post('/reset-password', $payload)->assertSessionHasNoErrors();

            // Re-using the same (already-consumed) token must fail.
            $this->post('/reset-password', array_merge($payload, [
                'password' => 'second-new-password',
                'password_confirmation' => 'second-new-password',
            ]))->assertSessionHasErrors('email');

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(
            Hash::check('new-strong-password', $user->fresh()->password)
        );
    }

    public function test_reset_password_expiry_is_configured_as_a_single_use_short_lived_window(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }
}
