<?php

namespace App\Notifications;

use App\Models\Course;
use App\Services\OrgUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * dispatched by `SendEnrollmentConfirmedNotification`
 * whenever a `course_user` row is created or transitions into `active`
 * (brand-new enrollment or a reactivated one), from the Gestor-driven
 * enrollment panel. `database` is listed before `mail` in
 * {@see self::via()} so the in-app row is guaranteed to persist even if
 * the `mail` channel's queued job throws.
 */
class EnrollmentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Course $course) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = OrgUrl::route($this->course->org_id, 'classroom.show', $this->course);

        return (new MailMessage)
            ->subject('Matrícula confirmada - '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Sua matrícula no curso "'.$this->course->title.'" foi confirmada.')
            ->line('Se você ainda não definiu sua senha, utilize o link de convite enviado pela sua organização para finalizar seu cadastro.')
            ->action('Acessar curso', $url)
            ->line('Bons estudos!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'Sua matrícula no curso "'.$this->course->title.'" foi confirmada.',
            'action_url' => OrgUrl::route($this->course->org_id, 'classroom.show', $this->course),
            'course_id' => $this->course->id,
        ];
    }
}
