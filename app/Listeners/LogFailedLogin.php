<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;

/**
 * auto-discovered listener for the stock
 * `Illuminate\Auth\Events\Failed` event. The attempting user is never
 * identified (bad credentials), so `org_id`/`user_id` both stay `null`
 * rather than guessing an Org from the unverified `email` string (see
 * `audit-logs-architecture`).
 */
class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        try {
            AuditService::log(
                event: 'login.failed',
                orgId: null,
                userId: null,
                payload: [
                    'email' => $event->credentials['email'] ?? null,
                    'status' => 'invalid_credentials',
                    'password' => '[REDACTED]',
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
