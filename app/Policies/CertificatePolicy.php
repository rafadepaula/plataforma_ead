<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\OrgContext;

/**
 * `Certificate` is cascade-inherited (org implied by
 * `course.org_id`) and has no `OrgScope` of its own (see
 * `tenancy-maintenance` (`resource/architecture.md`)), so this Policy is the only place a Gestor's
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

        return (int) OrgContext::current()->orgId() === (int) $course->org_id;
    }

    /**
     * Restoration of a logically revoked certificate follows exactly the
     * same boundary as revocation itself: `role:admin` unrestricted,
     * `role:gestor` only for a Certificate whose Course belongs to their
     * own Org (Professors and Alunos always `false`).
     */
    public function restore(User $user, Certificate $certificate): bool
    {
        return $this->revoke($user, $certificate);
    }

    /**
     * PDF download is broader than revocation: the Aluno who OWNS the
     * certificate (`user_id` match) is always allowed — the route sits
     * behind plain `auth` so the classroom's own "baixar certificado"
     * link works — and, beyond Admin/Gestor, `role:professor` of the same
     * Org may download their students' certificates too (same org-based
     * boundary as the Gestor; there is no professor→turma link consulted
     * on this path). Any other Aluno, or a staff member of another Org,
     * is denied. Revocation boundaries are unchanged — only `download`
     * admits Professors.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        if ((int) $user->id === (int) $certificate->user_id) {
            return true;
        }

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if (! $user->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::PROFESSOR->value])) {
            return false;
        }

        $course = $this->parentCourse($certificate);

        return (int) OrgContext::current()->orgId() === (int) $course->org_id;
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
