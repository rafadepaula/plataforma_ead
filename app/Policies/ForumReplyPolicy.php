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
        return $this->canCreateInCourse($user, $this->parentTopicCourse($topic));
    }

    public function update(User $user, ForumReply $reply): bool
    {
        if ((int) $reply->user_id === (int) $user->id) {
            return true;
        }

        return $this->canModerateCourse($user, $this->parentCourse($reply));
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
     * READ access to the reply. Admin: unrestricted. Gestor: only within
     * their own Org. Professor atribuído: leitura liberada
     * (`User::teaches()`). Aluno: only with an active/completed
     * enrollment in the Course.
     */
    protected function hasCourseAccess(User $user, Course $course): bool
    {
        if ($this->isGestorOrAdminForCourse($user, $course)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::PROFESSOR->value)) {
            return $user->teaches($course);
        }

        return $user->hasActiveOrCompletedEnrollment($course);
    }

    /**
     * WRITE access (posting a reply): deliberately NARROWER than
     * {@see self::hasCourseAccess()} — a Professor visualiza e modera,
     * mas não responde (evolução futura deliberada).
     */
    protected function canCreateInCourse(User $user, Course $course): bool
    {
        if ($this->isGestorOrAdminForCourse($user, $course)) {
            return true;
        }

        return $user->hasActiveOrCompletedEnrollment($course);
    }

    /**
     * Moderation over OTHERS' replies: Admin → true; Gestor same-org →
     * true; Professor atribuído → true (via `User::teaches()`).
     */
    protected function canModerateCourse(User $user, Course $course): bool
    {
        if ($this->isGestorOrAdminForCourse($user, $course)) {
            return true;
        }

        return $user->hasRole(RolesEnum::PROFESSOR->value) && $user->teaches($course);
    }

    protected function isGestorOrAdminForCourse(User $user, Course $course): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id === (int) $course->org_id;
    }
}
