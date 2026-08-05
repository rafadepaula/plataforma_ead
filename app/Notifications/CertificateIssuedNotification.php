<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SPEC-13 §2 gatilho 2 — dispatched from inside `IssueCertificateAction`
 * right after `Certificate::firstOrCreate` performs a genuine insert
 * (`wasRecentlyCreated`), never on the idempotent re-fetch/race-recovery
 * paths. `database` is listed before `mail` in {@see self::via()} so the
 * in-app row is guaranteed to persist even if the `mail` channel's queued
 * job throws (see SPEC-13 §3 / the `notifications-conventions` skill).
 */
class CertificateIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Certificate $certificate) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('certificates.verify', $this->certificate->validation_hash);

        return (new MailMessage)
            ->subject('Certificado emitido - '.config('app.name'))
            ->greeting('Parabéns, '.$notifiable->name.'!')
            ->line('Seu certificado do curso "'.$this->certificate->course->title.'" foi emitido.')
            ->action('Ver certificado', $url)
            ->line('Guarde este link para validar seu certificado a qualquer momento.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'Seu certificado do curso "'.$this->certificate->course->title.'" foi emitido.',
            'action_url' => route('certificates.verify', $this->certificate->validation_hash),
            'certificate_id' => $this->certificate->id,
        ];
    }
}
