<?php

namespace App\Services\Navigation;

use App\Models\ForumReport;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Pending-count badge resolvers for  RF38. Kept as a single
 * invokable collection (rather than inline closures inside the registry)
 * so they can be unit-tested in isolation and stay close to the Eloquent
 * queries they wrap.
 *
 * Both counters honour an active Admin "Impersonate Org" context
 * (`session('active_org_id')`) per RN41: `QuizAttempt` is org-scoped
 * transitively through its `Course` relation (its
 * `whereHas('quiz.lesson.module.course')` subquery picks up `Course`'s
 * `OrgScope`), and `ForumReport` — which carries no `OrgScope` of its
 * own — is scoped here via `ForumReport::postable()` plus the same
 * `ForumTopicPolicy`/`ForumReplyPolicy` `view` gate the moderation page
 * uses, so the badge never leaks a cross-tenant count. Because
 * `OrgScope` reads the acting user from `Auth::user()`, the resolver
 * leaves the resolved impersonating Admin authenticated while counting.
 */
final class NavigationBadges
{
    /**
     * Number of quiz attempts awaiting manual essay grading, scoped to
     * the acting user's tenant (or the impersonated Org for an Admin).
     *
     * @return int<0, max>
     */
    public function pendingEssayCount(User $user): int
    {
        return (int) QuizAttempt::query()
            ->where('status', 'awaiting_manual_grading')
            ->whereHas('quiz.lesson.module.course')
            ->count();
    }

    /**
     * Number of forum reports pending moderation, scoped to the acting
     * user's tenant (or the impersonated Org for an Admin).
     *
     * `ForumReport` carries no `OrgScope` of its own (pseudo-polymorphic,
     * no `org_id` column), so tenant scoping is enforced here exactly as
     * `ForumModerationController::index()` enforces it on the queue page:
     * resolve each pending report's target post (`ForumReport::postable()`,
     * `withTrashed()`) and keep only those the acting user can `view` via
     * `ForumTopicPolicy`/`ForumReplyPolicy`'s same-org gate. This keeps
     * the badge number in lock-step with the page it links to (RN41) and
     * prevents a cross-tenant count leak.
     *
     * @return int<0, max>
     */
    public function pendingForumReportCount(User $user): int
    {
        return (int) ForumReport::query()
            ->where('status', 'pending')
            ->get()
            ->filter(fn (ForumReport $report) => $report->postable() !== null && $user->can('view', $report->postable()))
            ->count();
    }
}
