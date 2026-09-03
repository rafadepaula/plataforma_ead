<?php

namespace App\Http\Controllers;

use App\Actions\DeleteForumPostAction;
use App\Actions\EditForumPostAction;
use App\Events\ForumReplyPosted;
use App\Http\Requests\StoreForumReplyRequest;
use App\Http\Requests\UpdateForumReplyRequest;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Services\ForumContentSanitizerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * `ForumReply` creation/edit/delete
 * (`store`/`update`/`destroy`) plus the AJAX `since_id` polling endpoint
 * (`fetchNew`, `throttle:60,1` — see `routes/web.php`). `ForumReply`
 * itself carries no `OrgScope` (cascade-inherited via its `ForumTopic`),
 * so only the parent `Course`/`ForumTopic` lookups need the
 * by-name `withoutGlobalScope('org')` bypass (see `ForumTopicController`'s
 * docblock for why it must never be the blanket `withoutGlobalScopes()`).
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

        $reply = ForumReply::query()->create([
            'topic_id' => $topicModel->id,
            'user_id' => $request->user()->id,
            'content' => $this->sanitizer->sanitize($request->validated('content')),
        ]);

        ForumReplyPosted::dispatch($reply);

        return redirect()->route('forum.show', [$course, $topic])
            ->with('success', 'Resposta publicada com sucesso.');
    }

    public function edit(Request $request, int $course, int $topic, int $reply): View
    {
        $topicModel = $this->resolveTopic($topic, $course);
        $replyModel = $this->resolveReply($reply, $topic);

        Gate::authorize('update', $replyModel);

        return view('forum.replies.edit', [
            'course' => $topicModel->course()->withoutGlobalScopes()->firstOrFail(),
            'topic' => $topicModel,
            'reply' => $replyModel,
        ]);
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
     * `fetchNewReplies` AJAX polling
     * (`ForumPolling.js`, every 10s), paginated by `since_id`: only rows
     * with `id > since_id` are ever returned, ordered ascending, capped at
     * 50 per call so a long-idle tab can't pull an unbounded backlog in
     * one request.
     *
     * The payload is a published contract consumed by `ForumPolling.js`,
     * which rebuilds the same card `forum.partials._reply` renders — so
     * every field that partial shows travels with the row, including the
     * author's `initials` (avatar fallback) and `role_label` (badge), both
     * read from `User`'s shared accessors, and the timestamp in BOTH of its
     * renderings — `created_at` absolute for the `title=` tooltip and
     * `created_at_relative` for the visible `diffForHumans()` text — so the
     * injected card and the server-rendered one can never drift:
     *
     *   {
     *     "data": [{
     *       "id": int, "content": string, "created_at": "d/m/Y H:i",
     *       "created_at_relative": "há 2 minutos",
     *       "initials": string, "role_label": string,
     *       "user": {"name": string}
     *     }],
     *     "last_id": int   // highest id in this batch, 0 when empty
     *   }
     *
     * `user.roles` is eager-loaded because `role_label` reads the role
     * relation for every row — without it a 10s poll would fire one
     * extra query per reply.
     */
    public function fetchNew(Request $request, int $course, int $topic): JsonResponse
    {
        $topicModel = $this->resolveTopic($topic, $course);

        Gate::authorize('view', $topicModel);

        $sinceId = (int) $request->query('since_id', 0);

        $replies = $topicModel->replies()
            ->where('id', '>', $sinceId)
            ->with('user.roles')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $replies->map(fn (ForumReply $reply): array => [
                'id' => $reply->id,
                'content' => $reply->content,
                'created_at' => $reply->created_at->format('d/m/Y H:i'),
                'created_at_relative' => $reply->created_at->diffForHumans(),
                'initials' => $reply->user->initials,
                'role_label' => $reply->user->role_label,
                'user' => ['name' => $reply->user->name],
            ])->values()->all(),
            'last_id' => (int) ($replies->max('id') ?? 0),
        ]);
    }

    protected function resolveTopic(int $topic, int $course): ForumTopic
    {
        $courseModel = Course::query()->withoutGlobalScope('org')->findOrFail($course);

        return ForumTopic::query()
            ->withoutGlobalScope('org')
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
