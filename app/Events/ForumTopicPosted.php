<?php

namespace App\Events;

use App\Models\ForumTopic;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by `ForumTopicController::store()` right after a new
 * `ForumTopic` row is created. `SendForumAnnouncementNotifications`
 * is the auto-discovered listener that notifies enrolled students
 * when a professor creates an announcement topic.
 */
class ForumTopicPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ForumTopic $topic) {}
}
