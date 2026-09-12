<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use App\Services\OrgContext;

/**
 * `Quiz` CRUD, same reach as `LessonPolicy` (Admin/Gestor plus the
 * Professor assigned to the Course — the Quiz is authored together with
 * its 1:1 Lesson, so whoever may edit the Lesson may configure its Quiz).
 * Cascade
 * -inherited two levels deeper than `Lesson` (`lesson -> module ->
 * course.org_id`), so this mirrors `LessonPolicy::parentCourse()` one
 * level further down.
 */
class QuizPolicy
{
    public function view(User $user, Quiz $quiz): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($quiz->lesson));
    }

    public function create(User $user, Lesson $lesson): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($lesson));
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($quiz->lesson));
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($quiz->lesson));
    }

    /**
     * Loads the great-grandparent `Course` bypassing `OrgScope` — see
     * `ModulePolicy::parentCourse()` for why this cannot use the plain
     * relation chain.
     */
    protected function parentCourse(Lesson $lesson): Course
    {
        return $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
    }

    /**
     * Mirrors `LessonPolicy::authorizeForCourse()` (one level deeper):
     * Admin unrestricted, Gestor same-org, and an assigned Professor
     * (`User::teaches()`) may author the Quiz of the Lessons they teach.
     */
    protected function authorizeForCourse(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return $user->hasRole(RolesEnum::PROFESSOR->value) && $user->teaches($course);
        }

        if ($user->hasRole(RolesEnum::GESTOR->value) && (int) OrgContext::current()->orgId() !== (int) $course->org_id) {
            return false;
        }

        return true;
    }
}
