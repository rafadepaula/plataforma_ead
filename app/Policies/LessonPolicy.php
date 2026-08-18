<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * same pattern as `ModulePolicy`, one level deeper:
 * `Lesson` is cascade-inherited via `Module` -> `Course`, so authorization
 * resolves the tenant through `$lesson->module->course` (or `$module->course`
 * on the `create` ability, where there is no `Lesson` instance yet).
 */
class LessonPolicy
{
    public function viewAny(User $user, Module $module): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($module));
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($lesson->module));
    }

    public function create(User $user, Module $module): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($module));
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($lesson->module));
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($lesson->module));
    }

    /**
     * Loads the grandparent `Course` bypassing `OrgScope` — see
     * `ModulePolicy::parentCourse()` for why this cannot use the plain
     * `$module->course` relation.
     */
    protected function parentCourse(Module $module): Course
    {
        return $module->course()->withoutGlobalScopes()->firstOrFail();
    }

    protected function authorizeForCourse(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return false;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id !== (int) $course->org_id) {
            return false;
        }

        return true;
    }
}
