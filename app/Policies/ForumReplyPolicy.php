<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * `ForumReply` is cascade-inherited two levels deeper
 * than `ForumTopic` (`reply -> topic -> course.org_id`), mirroring
 * `QuizPolicy::parentCourse()`'s cascade pattern one level further down.
 * `view`/`create` are gated to an enrolled Aluno  or a same-org
 * Gestor/Admin; `update`/`delete` are reserved to the reply's author (no
 * time limit, per §2.1) or a same-org Gestor/Admin. There is no `pin`
 * ability — replies have no `is_pinned` column.
 */
class ForumReplyPolicy
{
    public function view(User $user, ForumReply $reply): bool
    {
        return $this->hasCourseAccess($user, $this->parentCourse($reply));
    }

    public function create(User $user, ForumTopic $topic): bool
    {
        return $this->hasCourseAccess($user, $this->parentTopicCourse($topic));
    }

    public function update(User $user, ForumReply $reply): bool
    {
        if ((int) $reply->user_id === (int) $user->id) {
            return true;
        }

        return $this->isGestorOrAdminForCourse($user, $this->parentCourse($reply));
    }

    public function delete(User $user, ForumReply $reply): bool
    {
        return $this->update($user, $reply);
    }

    /**
     * Loads the grandparent `Course` bypassing `OrgScope` at both hops —
     * `ForumReply` has no `OrgScope` of its own, and `ForumTopic`'s own
     * `OrgScope` would otherwise silently filter out a cross-org topic,
     * turning the intended 403 into a type error (mirrors
     * `QuizPolicy::parentCourse()`).
     */
    protected function parentCourse(ForumReply $reply): Course
    {
        $topic = $reply->topic()->withoutGlobalScopes()->firstOrFail();

        return $this->parentTopicCourse($topic);
    }

    protected function parentTopicCourse(ForumTopic $topic): Course
    {
        return $topic->course()->withoutGlobalScopes()->firstOrFail();
    }

    /**
     * Admin: unrestricted. Gestor: only within their own Org. Aluno:
     * only with an active/completed enrollment in the Course .
     */
    protected function hasCourseAccess(User $user, Course $course): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return (int) $user->org_id === (int) $course->org_id;
        }

        return $user->hasActiveOrCompletedEnrollment($course);
    }

    protected function isGestorOrAdminForCourse(User $user, Course $course): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id === (int) $course->org_id;
    }
}
