<?php

namespace App\Actions;

use App\Models\ForumPostEdit;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * SPEC-10 §2.1/§2.2 — "apagar" a `ForumTopic`/`ForumReply` is a logical
 * removal-from-display (`SoftDeletes`), not a hard delete: this writes the
 * last content before removal into `forum_post_edits` (so a Gestor
 * reviewing history — or a moderation "Remover Publicação" action — can
 * still see what was posted), then soft-deletes the post.
 *
 * Deleting a topic does NOT cascade a soft-delete onto its replies — the
 * spec is silent on this, and once the parent topic's route resolves to a
 * 404 (soft-deleted rows are excluded from the default query), its
 * replies become unreachable through the UI regardless (see the plan's
 * edge cases).
 *
 * `postable_type` is written as the model's FQCN — same convention as
 * {@see EditForumPostAction}/{@see ReportForumPostAction}.
 */
class DeleteForumPostAction
{
    public function execute(ForumTopic|ForumReply $post, User $remover): void
    {
        ForumPostEdit::query()->create([
            'postable_type' => $post::class,
            'postable_id' => $post->id,
            'editor_user_id' => $remover->id,
            'previous_content' => $post->content,
            'edited_at' => now(),
        ]);

        $post->delete();
    }
}
