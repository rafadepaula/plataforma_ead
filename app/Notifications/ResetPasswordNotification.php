<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * localized (pt-BR) password-reset e-mail, delivered
 * through the SMTP mailer configured in `config/mail.php`. Reuses the
 * framework's single-use token + `resetUrl()` (built from the
 * `password.reset` named route) and only overrides the message content.
 */
class ResetPasswordNotification extends BaseResetPasswordNotification
{
    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Redefinição de Senha - '.config('app.name'))
            ->greeting('Olá!')
            ->line('Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta.')
            ->action('Redefinir Senha', $url)
            ->line('Este link de redefinição de senha expira em '.config('auth.passwords.users.expire').' minutos.')
            ->line('Se você não solicitou uma redefinição de senha, nenhuma ação adicional é necessária.');
    }
}
