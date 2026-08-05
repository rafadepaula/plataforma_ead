<?php

namespace App\Actions;

use App\Models\ForumPostEdit;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\ForumContentSanitizerService;

/**
 * SPEC-10 §2.1/§2.2 — the single place that enforces "preserve the
 * previous content in `forum_post_edits`" for both `ForumTopic` and
 * `ForumReply` edits: writes a `forum_post_edits` row carrying the
 * pre-edit content, then updates the post's `content` + `edited_at`.
 *
 * A resubmission with identical content still writes a history row and
 * bumps `edited_at` — the spec is silent on a no-op case, so this keeps
 * the rule simple and predictable rather than special-casing an empty
 * diff (see the plan's edge cases).
 *
 * `postable_type` is written as the model's FQCN (`ForumTopic::class`/
 * `ForumReply::class`) — see {@see ReportForumPostAction}'s docblock for
 * why this exact convention was chosen (`ForumPostEdit::postable()`
 * calls `$type::withTrashed()` directly on this column's value).
 */
class EditForumPostAction
{
    public function __construct(protected ForumContentSanitizerService $sanitizer) {}

    public function execute(ForumTopic|ForumReply $post, User $editor, string $content): ForumTopic|ForumReply
    {
        ForumPostEdit::query()->create([
            'postable_type' => $post::class,
            'postable_id' => $post->id,
            'editor_user_id' => $editor->id,
            'previous_content' => $post->content,
            'edited_at' => now(),
        ]);

        $post->update([
            'content' => $this->sanitizer->sanitize($content),
            'edited_at' => now(),
        ]);

        return $post->fresh();
    }
}
