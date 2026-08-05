<?php

namespace App\Notifications;

use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SPEC-13 §2 gatilho 3 — dispatched by `SendNewForumReplyNotifications`
 * for every recipient (topic author + prior distinct repliers, minus
 * whoever just posted) once per `ForumReply` created. `database` is
 * listed before `mail` in {@see self::via()} so the in-app row is
 * guaranteed to persist even if the `mail` channel's queued job throws.
 */
class NewForumReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ForumReply $reply) {}

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
        $url = route('forum.show', [$topic->course_id, $topic->id]);

        return (new MailMessage)
            ->subject('Nova resposta no fórum - '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line($this->reply->user->name.' respondeu ao tópico "'.$topic->title.'".')
            ->action('Ver resposta', $url)
            ->line('Você está recebendo este e-mail por participar deste tópico.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $topic = $this->topic();

        return [
            'message' => $this->reply->user->name.' respondeu ao tópico "'.$topic->title.'".',
            'action_url' => route('forum.show', [$topic->course_id, $topic->id]),
            'reply_id' => $this->reply->id,
        ];
    }

    /**
     * `ForumTopic` carries `OrgScope`; a queued/re-hydrated notification
     * sent to a multi-org Aluno (`org_id === null`) recipient would
     * otherwise resolve `$this->reply->topic` to `null` (see
     * `OrgScope::bootOrgScope()`), so the topic is fetched via
     * `withoutGlobalScopes()` here — mirroring
     * `SendNewForumReplyNotifications`'s own convention.
     */
    private function topic(): ForumTopic
    {
        return ForumTopic::query()->withoutGlobalScopes()->findOrFail($this->reply->topic_id);
    }
}
