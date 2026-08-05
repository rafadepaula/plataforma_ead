<?php

namespace App\Http\Controllers;

use App\Actions\ReportForumPostAction;
use App\Http\Requests\StoreForumReportRequest;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * SPEC-10 §2.2/RF26 — the "Denunciar" button
 * (`POST courses/{course}/forum/report`, `ForumReportModal.js`).
 *
 * The single boundary translating the `postable_type` value the request
 * carries — the short `forum_topic`/`forum_reply` labels the view/JS use
 * (`data-postable-type` on every "Denunciar" button) OR the raw FQCN
 * (`ForumTopic::class`/`ForumReply::class`, accepted by
 * `StoreForumReportRequest` for callers that already hold the model
 * class-string) — into the resolved `ForumTopic`/`ForumReply` model, then
 * into the FQCN `ForumReport::postable()` (Bucket 1) actually needs to
 * resolve it back (`$type::withTrashed()->find(...)` — a real
 * class-string, not a short label). `ReportForumPostAction` is never
 * called with the raw short string itself.
 */
class ForumReportController extends Controller
{
    public function __construct(protected ReportForumPostAction $reportForumPostAction) {}

    public function store(StoreForumReportRequest $request, int $course): JsonResponse
    {
        $postable = $this->resolvePostable(
            $request->validated('postable_type'),
            (int) $request->validated('postable_id'),
        );

        Gate::authorize('view', $postable);

        $this->reportForumPostAction->execute($postable, $request->user(), $request->validated('reason'));

        return response()->json(['message' => 'Denúncia enviada. A moderação irá revisar.'], 201);
    }

    protected function resolvePostable(string $postableType, int $postableId): ForumTopic|ForumReply
    {
        $modelClass = match ($postableType) {
            'forum_topic', ForumTopic::class => ForumTopic::class,
            default => ForumReply::class,
        };

        return $modelClass::query()->withoutGlobalScopes()->findOrFail($postableId);
    }
}
