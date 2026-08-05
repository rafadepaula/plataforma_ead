<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\User;

/**
 * SPEC-05 §1 — Course CRUD is reserved to `role:admin|gestor`.
 * `OrgScope` already keeps a Gestor's queries confined to their own
 * `org_id`, so this Policy only needs the role check plus the delete-time
 * active-enrollment guard; it does not re-verify `org_id` itself (contrast
 * with `ModulePolicy`/`LessonPolicy`, which have no scope of their own to
 * rely on).
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function view(User $user, Course $course): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    /**
     * Denies (returns `false`, not an exception) when the Course has at
     * least one `active` enrollment, so `Gate::authorize`/`@can` short
     * -circuit with a plain 403 before the controller's own 422 guard
     * (`CourseHasActiveEnrollmentsException`) would otherwise fire.
     */
    public function delete(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return false;
        }

        return ! $course->hasActiveEnrollments();
    }
}
