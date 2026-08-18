<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Login;

/**
 * auto-discovered (no `EventServiceProvider` in this
 * codebase, see `audit-logs-architecture`) listener for the stock
 * `Illuminate\Auth\Events\Login` event, fired on every successful
 * authentication. Logs `login.success` with the actual password value
 * never touched — only the fixed `'[REDACTED]'` placeholder is recorded
 * (RN14).
 */
class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        try {
            AuditService::log(
                event: 'login.success',
                orgId: $user->org_id ? (int) $user->org_id : null,
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
