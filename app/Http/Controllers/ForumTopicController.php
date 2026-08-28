<?php

namespace App\Http\Controllers;

use App\Actions\DeleteForumPostAction;
use App\Actions\EditForumPostAction;
use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\StoreForumTopicRequest;
use App\Http\Requests\UpdateForumTopicRequest;
use App\Models\Course;
use App\Models\ForumPostEdit;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\ForumContentSanitizerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * per-course `ForumTopic` list (`index`), creation
 * (`create`/`store`), thread view (`show`), edit/delete
 * (`edit`/`update`/`destroy`), and the Gestor/Admin-only pin toggle (`pin`).
 * Sits behind `student.enrolled` for every action except `pin` (see
 * `routes/web.php`'s `role:admin|gestor` group).
 *
 * `{course}`/`{topic}` are always plain `int` route parameters, never a
 * typed `Course`/`ForumTopic` implicit binding: `ForumTopic` carries
 * `OrgScope`, and a multi-org Aluno (, `org_id === null`, no
 * impersonation session) would have every query silently filtered to
 * nothing under that scope (see `OrgScope::bootOrgScope()`) — every
 * lookup here explicitly bypasses it via `withoutGlobalScopes()`, mirrors
 * `ClassroomController`/`StudentQuizController`'s same convention for
 * `Course`.
 */
class ForumTopicController extends Controller
{
    public function __construct(
        protected ForumContentSanitizerService $sanitizer,
        protected EditForumPostAction $editForumPostAction,
        protected DeleteForumPostAction $deleteForumPostAction,
    ) {}

    public function index(Request $request, int $course): View
    {
        $courseModel = $this->resolveCourse($course);
        $user = $request->user();

        Gate::authorize('viewAny', [ForumTopic::class, $courseModel]);

        $topics = ForumTopic::query()
            ->withoutGlobalScopes()
            ->where('course_id', $courseModel->id)
            ->with('user')
            ->withCount('replies')
            ->pinnedFirst()
            ->paginate(15);

        return view('forum.index', [
            'course' => $courseModel,
            'topics' => $topics,
            'canCreateTopic' => (bool) $user->can('create', [ForumTopic::class, $courseModel]),
            'canPin' => $this->isGestorOrAdminForCourse($user, $courseModel),
        ]);
    }

    public function create(Request $request, int $course): View
    {
        $courseModel = $this->resolveCourse($course);
        Gate::authorize('create', [ForumTopic::class, $courseModel]);

        return view('forum.create', ['course' => $courseModel]);
    }

    /**
     * `ForumTopic` carries `OrgScope`, whose `creating` hook (see
     * `OrgScope::booted()`) unconditionally overwrites `org_id` by
     * resolving `$user->org_id ?? session('active_org_id')` — a multi-org
     * Aluno has neither, so it would throw
     * `UnresolvedOrgContextException` even though the target Course's
     * tenant is perfectly well known here. `ForumTopic::withoutEvents()`
     * skips that hook for this single write so the Course's own `org_id`
     * is assigned directly instead — the only place in this bucket that
     * needs the workaround, since every other forum write either targets
     * a non-`OrgScope`d model (`ForumReply`/`ForumPostEdit`/`ForumReport`)
     * or updates (not creates) an existing `ForumTopic` row.
     */
    public function store(StoreForumTopicRequest $request, int $course): RedirectResponse
    {
        $courseModel = $this->resolveCourse($course);
        Gate::authorize('create', [ForumTopic::class, $courseModel]);

        $topic = ForumTopic::withoutEvents(fn () => ForumTopic::query()->create([
            'org_id' => $courseModel->org_id,
            'course_id' => $courseModel->id,
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'content' => $this->sanitizer->sanitize($request->validated('content')),
        ]));

        return redirect()->route('forum.show', [$courseModel->id, $topic->id])
            ->with('success', 'Tópico criado com sucesso.');
    }

    public function show(Request $request, int $course, int $topic): View
    {
        $courseModel = $this->resolveCourse($course);
        $topicModel = $this->resolveTopic($topic, $courseModel);
        $user = $request->user();

        Gate::authorize('view', $topicModel);

        $topicModel->setRelation('course', $courseModel);
        $topicModel->load('user.roles');

        $replies = $topicModel->replies()->with('user.roles')->orderBy('id')->get();

        $topicEditHistory = ForumPostEdit::query()
            ->with('editor')
            ->where('postable_type', ForumTopic::class)
            ->where('postable_id', $topicModel->id)
            ->orderByDesc('edited_at')
            ->get();

        $replyEditHistories = ForumPostEdit::query()
            ->with('editor')
            ->where('postable_type', ForumReply::class)
            ->whereIn('postable_id', $replies->pluck('id'))
            ->orderByDesc('edited_at')
            ->get()
            ->groupBy('postable_id');

        return view('forum.show', [
            'course' => $courseModel,
            'topic' => $topicModel,
            'replies' => $replies,
            'topicEditHistory' => $topicEditHistory,
            'replyEditHistories' => $replyEditHistories,
            'canEditTopic' => (bool) $user->can('update', $topicModel),
            'canDeleteTopic' => (bool) $user->can('delete', $topicModel),
            'canPin' => (bool) $user->can('pin', $topicModel),
            'canModerate' => $this->isGestorOrAdminForCourse($user, $courseModel),
            'lastReplyId' => (int) ($replies->max('id') ?? 0),
        ]);
    }

    public function edit(Request $request, int $course, int $topic): View
    {
        $courseModel = $this->resolveCourse($course);
        $topicModel = $this->resolveTopic($topic, $courseModel);

        Gate::authorize('update', $topicModel);

        return view('forum.edit', ['course' => $courseModel, 'topic' => $topicModel]);
    }

    public function update(UpdateForumTopicRequest $request, int $course, int $topic): RedirectResponse
    {
        $courseModel = $this->resolveCourse($course);
        $topicModel = $this->resolveTopic($topic, $courseModel);

        Gate::authorize('update', $topicModel);

        $this->editForumPostAction->execute($topicModel, $request->user(), $request->validated('content'));
        $topicModel->update(['title' => $request->validated('title')]);

        return redirect()->route('forum.show', [$course, $topicModel->id])
            ->with('success', 'Tópico atualizado com sucesso.');
    }

    public function destroy(Request $request, int $course, int $topic): RedirectResponse
    {
        $courseModel = $this->resolveCourse($course);
        $topicModel = $this->resolveTopic($topic, $courseModel);

        Gate::authorize('delete', $topicModel);

        $this->deleteForumPostAction->execute($topicModel, $request->user());

        return redirect()->route('forum.index', $course)
            ->with('success', 'Tópico removido com sucesso.');
    }

    public function pin(int $course, int $topic): RedirectResponse
    {
        $courseModel = $this->resolveCourse($course);
        $topicModel = $this->resolveTopic($topic, $courseModel);

        Gate::authorize('pin', $topicModel);

        $topicModel->update(['is_pinned' => ! $topicModel->is_pinned]);

        return redirect()->route('forum.show', [$course, $topicModel->id])
            ->with('success', $topicModel->is_pinned ? 'Tópico fixado.' : 'Tópico desafixado.');
    }

    protected function resolveCourse(int $course): Course
    {
        return Course::query()->withoutGlobalScopes()->findOrFail($course);
    }

    protected function resolveTopic(int $topic, Course $course): ForumTopic
    {
        return ForumTopic::query()
            ->withoutGlobalScopes()
            ->where('course_id', $course->id)
            ->findOrFail($topic);
    }

    protected function isGestorOrAdminForCourse(User $user, Course $course): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id === (int) $course->org_id;
    }
}
