<?php

namespace App\Events;

use App\Models\ForumReply;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SPEC-13 §2 gatilho 3 — dispatched by `ForumReplyController::store()`
 * right after a new `ForumReply` row is created.
 * `SendNewForumReplyNotifications` is the sole listener, auto-discovered
 * from its `handle()` type-hint.
 */
class ForumReplyPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ForumReply $reply) {}
}
