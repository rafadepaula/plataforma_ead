<?php

namespace App\Listeners;

use App\Events\InvitationLinkCreated;
use App\Notifications\InvitationSentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * SPEC-13 §2 gatilho 1 — auto-discovered (type-hinted `handle()`
 * parameter). Sends `InvitationSentNotification` to the link creator's
 * e-mail via `Notification::route('mail', $email)` — there is no `User`
 * "invitee" yet to call `->notify()` on. Per SPEC-13 §3/RN, a mail
 * transport failure must never bubble up and abort the request that
 * created the `InvitationLink`, so the send is wrapped in try/catch and
 * logged rather than left to propagate.
 */
class SendInvitationSentNotification
{
    public function handle(InvitationLinkCreated $event): void
    {
        try {
            Notification::route('mail', $event->invitationLink->creator->email)
                ->notify(new InvitationSentNotification($event->invitationLink));
        } catch (Throwable $exception) {
            Log::error('Falha ao enviar notificação de convite criado.', [
                'invitation_link_id' => $event->invitationLink->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
