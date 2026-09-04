<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;

/**
 * `Module` is cascade-inherited and has no `OrgScope` of its own
 * (see `courses-architecture`), so this Policy is the only place a
 * cross-tenant Module access attempt (e.g. a Gestor guessing another
 * org's `/courses/{course}/modules/{module}` URL) gets rejected — defense
 * in depth on top of `Course`'s own `OrgScope`, which already keeps route
 * -model-bound `{course}` scoped to the acting user.
 */
class ModulePolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $this->authorizeForCourse($user, $course);
    }

    public function view(User $user, Module $module): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($module));
    }

    public function create(User $user, Course $course): bool
    {
        return $this->authorizeForCourse($user, $course);
    }

    public function update(User $user, Module $module): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($module));
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($module));
    }

    /**
     * Loads the parent `Course` bypassing `OrgScope`. Since the module's
     * own query was resolved via route-model binding (also unscoped —
     * `Module` carries no `OrgScope` itself), the relation must be read
     * without the scope too, otherwise a Gestor from a *different* org
     * would see `$module->course` come back `null` (filtered out by the
     * scope) instead of a real "different tenant" `Course` to compare
     * `org_id` against — turning the intended 403 into a type error.
     */
    protected function parentCourse(Module $module): Course
    {
        return $module->course()->withoutGlobalScopes()->firstOrFail();
    }

    /**
     * Role check identical to `CoursePolicy`, plus an explicit
     * `org_id` comparison for a Gestor — `Module` has no `OrgScope` to
     * fall back on, so this must be verified here rather than assumed.
     *
     * An assigned Professor gets full content authoring on the Course's
     * modules (`User::teaches()` being the single source of that
     * assignment) — but never the Course's own metadata, which stays
     * `CoursePolicy`-gated (`courses.edit` remains 403 to them).
     */
    protected function authorizeForCourse(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return $user->hasRole(RolesEnum::PROFESSOR->value) && $user->teaches($course);
        }

        if ($user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id !== (int) $course->org_id) {
            return false;
        }

        return true;
    }
}
