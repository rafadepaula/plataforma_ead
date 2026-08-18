<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * `ForumTopic` is directly `OrgScope`d, but course
 * access still must be checked explicitly here: `view`/`create` are
 * gated to an enrolled Aluno  or a same-org Gestor/Admin;
 * `update`/`delete` are reserved to the post's author (no time limit,
 * per §2.1) or a same-org Gestor/Admin; `pin` is Gestor/Admin-only
 * (§2/§2.2 — direct moderation, independent of any report).
 */
class ForumTopicPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $this->hasCourseAccess($user, $course);
    }

    public function view(User $user, ForumTopic $topic): bool
    {
        return $this->hasCourseAccess($user, $this->parentCourse($topic));
    }

    public function create(User $user, Course $course): bool
    {
        return $this->hasCourseAccess($user, $course);
    }

    public function update(User $user, ForumTopic $topic): bool
    {
        if ((int) $topic->user_id === (int) $user->id) {
            return true;
        }

        return $this->isGestorOrAdminForCourse($user, $this->parentCourse($topic));
    }

    public function delete(User $user, ForumTopic $topic): bool
    {
        return $this->update($user, $topic);
    }

    public function pin(User $user, ForumTopic $topic): bool
    {
        return $this->isGestorOrAdminForCourse($user, $this->parentCourse($topic));
    }

    /**
     * Loads the parent `Course` bypassing `OrgScope` — a cross-org
     * Gestor's `$topic->course` must still resolve to a real `Course` to
     * compare `org_id` against (mirrors `ModulePolicy::parentCourse()`),
     * rather than come back `null` and turn the intended 403 into a
     * type error.
     */
    protected function parentCourse(ForumTopic $topic): Course
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
