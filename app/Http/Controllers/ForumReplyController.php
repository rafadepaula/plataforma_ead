<?php

namespace App\Http\Controllers;

use App\Actions\DeleteForumPostAction;
use App\Actions\EditForumPostAction;
use App\Http\Requests\StoreForumReplyRequest;
use App\Http\Requests\UpdateForumReplyRequest;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Services\ForumContentSanitizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * SPEC-10 §2/RF22 — `ForumReply` creation/edit/delete
 * (`store`/`update`/`destroy`) plus the AJAX `since_id` polling endpoint
 * (`fetchNew`, `throttle:60,1` — see `routes/web.php`). `ForumReply`
 * itself carries no `OrgScope` (cascade-inherited via its `ForumTopic`),
 * so only the parent `Course`/`ForumTopic` lookups need
 * `withoutGlobalScopes()` (see `ForumTopicController`'s docblock).
 */
class ForumReplyController extends Controller
{
    public function __construct(
        protected ForumContentSanitizerService $sanitizer,
        protected EditForumPostAction $editForumPostAction,
        protected DeleteForumPostAction $deleteForumPostAction,
    ) {}

    public function store(StoreForumReplyRequest $request, int $course, int $topic): RedirectResponse
    {
        $topicModel = $this->resolveTopic($topic, $course);

        // `Gate::authorize('create', $topicModel)` alone would resolve the
        // policy by $topicModel's own class (`ForumTopicPolicy`, whose
        // `create(User, Course)` signature doesn't match a `ForumTopic`
        // argument) instead of `ForumReplyPolicy::create(User, ForumTopic)`
        // — the `[ForumReply::class, $topicModel]` pair is required so
        // Laravel resolves the policy registered for `ForumReply` instead.
        Gate::authorize('create', [ForumReply::class, $topicModel]);

        ForumReply::query()->create([
            'topic_id' => $topicModel->id,
            'user_id' => $request->user()->id,
            'content' => $this->sanitizer->sanitize($request->validated('content')),
        ]);

        return redirect()->route('forum.show', [$course, $topic])
            ->with('success', 'Resposta publicada com sucesso.');
    }

    public function update(UpdateForumReplyRequest $request, int $course, int $topic, int $reply): RedirectResponse
    {
        $replyModel = $this->resolveReply($reply, $topic);

        Gate::authorize('update', $replyModel);

        $this->editForumPostAction->execute($replyModel, $request->user(), $request->validated('content'));

        return redirect()->route('forum.show', [$course, $topic])
            ->with('success', 'Resposta atualizada com sucesso.');
    }

    public function destroy(Request $request, int $course, int $topic, int $reply): RedirectResponse
    {
        $replyModel = $this->resolveReply($reply, $topic);

        Gate::authorize('delete', $replyModel);

        $this->deleteForumPostAction->execute($replyModel, $request->user());

        return redirect()->route('forum.show', [$course, $topic])
            ->with('success', 'Resposta removida com sucesso.');
    }

    /**
     * SPEC-10 §2 — `fetchNewReplies` AJAX polling
     * (`ForumPolling.js`, every 10s), paginated by `since_id`: only rows
     * with `id > since_id` are ever returned, ordered ascending, capped at
     * 50 per call so a long-idle tab can't pull an unbounded backlog in
     * one request.
     */
    public function fetchNew(Request $request, int $course, int $topic): JsonResponse
    {
        $topicModel = $this->resolveTopic($topic, $course);

        Gate::authorize('view', $topicModel);

        $sinceId = (int) $request->query('since_id', 0);

        $replies = $topicModel->replies()
            ->where('id', '>', $sinceId)
            ->with('user')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $replies->map(fn (ForumReply $reply): array => [
                'id' => $reply->id,
                'content' => $reply->content,
                'created_at' => $reply->created_at->format('d/m/Y H:i'),
                'user' => ['name' => $reply->user->name],
            ])->values()->all(),
        ]);
    }

    protected function resolveTopic(int $topic, int $course): ForumTopic
    {
        $courseModel = Course::query()->withoutGlobalScopes()->findOrFail($course);

        return ForumTopic::query()
            ->withoutGlobalScopes()
            ->where('course_id', $courseModel->id)
            ->findOrFail($topic);
    }

    protected function resolveReply(int $reply, int $topic): ForumReply
    {
        return ForumReply::query()
            ->where('topic_id', $topic)
            ->findOrFail($reply);
    }
}
