<?php

namespace App\Notifications;

use App\Models\InvitationLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SPEC-13 §2 gatilho 1 — dispatched right after an `InvitationLink` is
 * created (`InvitationLinkController::store()`), via
 * `SendInvitationSentNotification`. `mail` only: `invitation_links` has no
 * per-invitee email column (it is a shareable link, not a per-person
 * invite), so this confirms creation to the link's creator
 * (`InvitationLink::creator`) via `Notification::route('mail', $email)`
 * rather than `$user->notify()` — there is no `database` channel/row for
 * this trigger by design (see SPEC-13 §2's table).
 */
class InvitationSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InvitationLink $invitationLink) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.show', $this->invitationLink->token);

        return (new MailMessage)
            ->subject('Link de convite criado - '.config('app.name'))
            ->greeting('Olá!')
            ->line('Um novo link de convite foi gerado para o curso "'.$this->invitationLink->course->title.'".')
            ->line('Uso máximo: '.($this->invitationLink->max_uses ?? 'ilimitado').'.')
            ->action('Ver link de convite', $url)
            ->line('Compartilhe este link com os alunos que deseja matricular.');
    }
}
