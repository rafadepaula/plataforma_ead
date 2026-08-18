<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;

/**
 * `Certificate` is cascade-inherited (org implied by
 * `course.org_id`) and has no `OrgScope` of its own (see
 * `tenancy-architecture`), so this Policy is the only place a Gestor's
 * cross-tenant revocation attempt gets rejected — mirrors
 * `ModulePolicy`'s cascade-authorize style.
 */
class CertificatePolicy
{
    /**
     * `role:admin` is unrestricted; `role:gestor` only for a Certificate
     * whose Course belongs to their own Org.
     */
    public function revoke(User $user, Certificate $certificate): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if (! $user->hasRole(RolesEnum::GESTOR->value)) {
            return false;
        }

        $course = $this->parentCourse($certificate);

        return (int) $user->org_id === (int) $course->org_id;
    }

    /**
     * Loads the parent `Course` bypassing `OrgScope` — same reasoning as
     * `ModulePolicy::parentCourse()`: the Certificate itself carries no
     * scope, so its `course` relation must be read unscoped too, otherwise
     * a Gestor from a *different* org would see `null` instead of a real
     * "different tenant" Course to compare `org_id` against.
     */
    private function parentCourse(Certificate $certificate): Course
    {
        return $certificate->course()->withoutGlobalScopes()->firstOrFail();
    }
}
