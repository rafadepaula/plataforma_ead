<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

/**
 * SPEC-15 §3 — auto-discovered listener for the stock
 * `Illuminate\Auth\Events\Logout` event.
 */
class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if ($user === null) {
            return;
        }

        try {
            AuditService::log(
                event: 'logout',
                orgId: $user->org_id ? (int) $user->org_id : null,
                userId: (int) $user->getAuthIdentifier(),
                payload: [
                    'user_id' => $user->getAuthIdentifier(),
                    'email' => $user->email,
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
