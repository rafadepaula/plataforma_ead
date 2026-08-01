<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\User;

/**
 * SPEC-06 RF03 — `InvitationLink` management (generate/revoke) is reserved
 * to `role:admin|gestor`. `InvitationLink` carries its own `OrgScope` (like
 * `Course`), so — mirroring `CoursePolicy` rather than `ModulePolicy` —
 * this Policy only needs the role check; `OrgScope` already keeps a
 * Gestor's queries confined to their own `org_id`.
 */
class InvitationLinkPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $this->authorize($user, $course);
    }

    public function create(User $user, Course $course): bool
    {
        return $this->authorize($user, $course);
    }

    public function delete(User $user, InvitationLink $invitationLink): bool
    {
        return $this->authorize($user, $invitationLink->course()->withoutGlobalScopes()->firstOrFail());
    }

    protected function authorize(User $user, Course $course): bool
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
