<?php

namespace App\Http\Controllers;

use App\Actions\DeleteForumPostAction;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * the Gestor/Admin pending-report queue
 * (`GET forum/moderation`), plus `dismiss`/`remove` per report.
 * Restricted to `role:admin|gestor` (see `routes/web.php`); `ForumReport`
 * carries no `OrgScope` of its own (pseudo-polymorphic, no `org_id`
 * column), so tenant scoping is enforced here by resolving each pending
 * report's target post (`ForumReport::postable()`, `withTrashed()` —
 * Bucket 1) and reusing `ForumTopicPolicy`/`ForumReplyPolicy`'s existing
 * same-org gates (`view` for the read-only queue, `delete` for
 * `remove()`) rather than duplicating the org-comparison logic here.
 *
 * A report whose `postable()` can no longer be resolved at all (its
 * `postable_id` refers to a row hard-deleted by a cascading `Course`
 * removal, not merely soft-deleted) is silently excluded from the queue
 * rather than fatally erroring — see the plan's edge cases.
 */
class ForumModerationController extends Controller
{
    public function __construct(protected DeleteForumPostAction $deleteForumPostAction) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $reports = ForumReport::query()
            ->where('status', 'pending')
            ->with('reporter')
            ->orderBy('created_at')
            ->get()
            ->filter(function (ForumReport $report) use ($user): bool {
                $postable = $this->resolvePostable($report);

                return $postable !== null && $user->can('view', $postable);
            })
            ->values();

        $reports->each(function (ForumReport $report): void {
            $report->setRelation('postable', $this->resolvePostable($report));
        });

        return view('forum.moderation.index', ['reports' => $reports]);
    }

    public function dismiss(Request $request, ForumReport $forumReport): RedirectResponse
    {
        $postable = $this->resolvePostable($forumReport);
        abort_if($postable === null, 404);

        Gate::authorize('view', $postable);

        $forumReport->update([
            'status' => 'reviewed_dismissed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('forum-moderation.index')->with('success', 'Denúncia dispensada.');
    }

    public function remove(Request $request, ForumReport $forumReport): RedirectResponse
    {
        $postable = $this->resolvePostable($forumReport);
        abort_if($postable === null, 404);

        Gate::authorize('delete', $postable);

        $this->deleteForumPostAction->execute($postable, $request->user());

        $forumReport->update([
            'status' => 'reviewed_removed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('forum-moderation.index')
            ->with('success', 'Conteúdo removido e denúncia marcada como resolvida.');
    }

    protected function resolvePostable(ForumReport $report): ForumTopic|ForumReply|null
    {
        return $report->postable();
    }
}
