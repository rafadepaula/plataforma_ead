<?php

namespace App\Actions;

use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\ForumContentSanitizerService;

/**
 * SPEC-10 §2.2/RF26 — creates a pending `forum_reports` row for a
 * "Denunciar" submission against a `ForumTopic` or `ForumReply`.
 *
 * `postable_type` is written as the model's FQCN (`ForumTopic::class`/
 * `ForumReply::class`) — `ForumReport::postable()` (Bucket 1) calls
 * `$type::withTrashed()->find(...)` directly on this column's value, so it
 * must be a real class-string, not a short label. `ForumReportController`
 * is the single boundary that translates the `forum_topic`/`forum_reply`
 * short strings the HTTP request/views/JS use into this FQCN before this
 * Action ever runs — see its docblock for the full cross-bucket note.
 * {@see EditForumPostAction}/{@see DeleteForumPostAction} keep the same
 * convention on the `forum_post_edits` write side.
 */
class ReportForumPostAction
{
    public function __construct(protected ForumContentSanitizerService $sanitizer) {}

    public function execute(ForumTopic|ForumReply $postable, User $reporter, string $reason): ForumReport
    {
        return ForumReport::query()->create([
            'postable_type' => $postable::class,
            'postable_id' => $postable->id,
            'reported_by' => $reporter->id,
            'reason' => $this->sanitizer->sanitize($reason),
            'status' => 'pending',
        ]);
    }
}
