<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use App\Services\OrgContext;
use Illuminate\Auth\Events\PasswordReset;

/**
 * auto-discovered listener for the stock
 * `Illuminate\Auth\Events\PasswordReset` event, fired by
 * `NewPasswordController::store()` once the single-use reset token is
 * consumed. Covers the completion stage only — see
 * `audit-logs-architecture` for why the request stage
 * (`PasswordResetLinkController::store()`) does not also emit an audit
 * event under the same `password.reset` name.
 */
class LogPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        try {
            AuditService::log(
                event: 'password.reset',
                orgId: OrgContext::current()->orgId(),
                userId: (int) $user->getAuthIdentifier(),
                payload: [
                    'user_id' => $user->getAuthIdentifier(),
                    'email' => $user->email,
                    'password' => '[REDACTED]',
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
