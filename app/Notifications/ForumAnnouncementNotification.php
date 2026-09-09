<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\ForumTopic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dispatched by `SendForumAnnouncementNotifications` to every enrolled student
 * when an assigned professor creates a new forum topic (announcement).
 * `database` is listed before `mail` in via() so the in-app bell row is
 * guaranteed to persist even if mail transport throws.
 */
class ForumAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ForumTopic $topic,
        public Course $course,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $topic = $this->topic();
        $course = $this->course();
        $url = route('forum.show', [$course->id, $topic->id]);

        return (new MailMessage)
            ->subject('Novo anúncio no fórum - '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('O professor '.$topic->user->name.' publicou um novo tópico no curso "'.$course->title.'":')
            ->line('"'.$topic->title.'"')
            ->action('Ver tópico', $url)
            ->line('Você está recebendo este e-mail por estar matriculado neste curso.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $topic = $this->topic();
        $course = $this->course();

        return [
            'message' => 'O professor '.$topic->user->name.' publicou um novo tópico: "'.$topic->title.'".',
            'action_url' => route('forum.show', [$course->id, $topic->id]),
            'topic_id' => $topic->id,
            'course_id' => $course->id,
        ];
    }

    private function topic(): ForumTopic
    {
        return ForumTopic::query()->withoutGlobalScopes()->findOrFail($this->topic->id);
    }

    private function course(): Course
    {
        return Course::query()->withoutGlobalScopes()->findOrFail($this->course->id);
    }
}
