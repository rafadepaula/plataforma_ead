<?php

namespace App\Listeners;

use App\Events\ForumReplyPosted;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\NewForumReplyNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * auto-discovered (type-hinted `handle()`
 * parameter). Recipients are the topic author plus every distinct prior
 * replier in the same topic, minus whoever just posted this reply — a
 * `Set`-style dedupe via `unique()` guarantees the topic author, even if
 * they had also replied earlier, gets exactly one notification, not two.
 * 's `->notify()` call is wrapped in its
 * own try/catch so one recipient's mail transport failure never prevents
 * the others (or the `database` row `CertificateIssuedNotification`-style
 * channel ordering already guarantees) from being delivered.
 *
 * `ForumTopic` carries `OrgScope`; a multi-org Aluno (`org_id === null`)
 * posting the reply that triggers this listener would otherwise zero out
 * `$reply->topic` entirely (see `OrgScope::bootOrgScope()`), so the topic
 * is resolved via `withoutGlobalScopes()` here — mirroring
 * `ForumReplyController::resolveTopic()`'s own convention — rather than
 * through the scoped `belongsTo` relation.
 */
class SendNewForumReplyNotifications
{
    public function handle(ForumReplyPosted $event): void
    {
        $reply = $event->reply;
        $topic = ForumTopic::query()->withoutGlobalScopes()->findOrFail($reply->topic_id);

        $recipientIds = collect([$topic->user_id])
            ->merge(
                ForumReply::query()
                    ->where('topic_id', $topic->id)
                    ->where('id', '!=', $reply->id)
                    ->pluck('user_id')
            )
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $reply->user_id)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new NewForumReplyNotification($reply));
            } catch (Throwable $exception) {
                Log::error('Falha ao enviar notificação de nova resposta no fórum.', [
                    'reply_id' => $reply->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
