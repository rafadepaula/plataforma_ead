<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\OrgContext;

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
        return $this->canCreateInCourse($user, $course);
    }

    public function update(User $user, ForumTopic $topic): bool
    {
        if ((int) $topic->user_id === (int) $user->id) {
            return true;
        }

        return $this->canModerateCourse($user, $this->parentCourse($topic));
    }

    public function delete(User $user, ForumTopic $topic): bool
    {
        return $this->update($user, $topic);
    }

    public function pin(User $user, ForumTopic $topic): bool
    {
        return $this->canModerateCourse($user, $this->parentCourse($topic));
    }

    /**
     * Reporting is for peers, not for self-moderation and not against
     * staff: the author themself can never report their own topic, and a
     * topic authored by staff (Admin/Gestor/Professor) is out of the
     * report flow entirely — students take those concerns to the
     * organization, which IS the moderation chain for staff content.
     * Still requires plain course `view` access.
     */
    public function report(User $user, ForumTopic $topic): bool
    {
        if ((int) $topic->user_id === (int) $user->id) {
            return false;
        }

        if ($topic->user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value, RolesEnum::PROFESSOR->value])) {
            return false;
        }

        return $this->view($user, $topic);
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
     * READ access to a Course's forum. Admin: unrestricted. Gestor: only
     * within their own Org. Professor atribuído: leitura liberada
     * (`User::teaches()` — visualizar é parte do papel docente).
     * Aluno: only with an active/completed enrollment in the Course.
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
     * WRITE access (posting a new topic): Gestor/Admin, Professor assigned
     * to the Course (`User::teaches()`), or Aluno with an active/completed
     * enrollment.
     */
    protected function canCreateInCourse(User $user, Course $course): bool
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
     * Moderation over OTHERS' posts: Admin → true; Gestor same-org →
     * true; Professor atribuído → true. Feeds `update`/`delete` of
     * foreign posts and `pin` — the same actions the two staff roles
     * already had, now shared with the assigned docente.
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

        return $user->hasRole(RolesEnum::GESTOR->value) && (int) OrgContext::current()->orgId() === (int) $course->org_id;
    }
}
