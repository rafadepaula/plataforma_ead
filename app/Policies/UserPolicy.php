<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;

/**
 * RF04 — Aluno/Gestor CRUD authorization. Admin manages any Organization
 * (scoped by the impersonated `session('active_org_id')`); Gestor manages
 * only their own `org_id`; Aluno has no access at all. Mirrors the same
 * tenant-boundary resolution `OrgScope` uses elsewhere (see
 * `tenancy-conventions`), but User is intentionally never `OrgScope`d
 * itself (see `App\Models\User`'s docblock), so this policy is the
 * enforcement point instead of a global scope.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function view(User $user, User $model): bool
    {
        return $this->sharesOrgContext($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function update(User $user, User $model): bool
    {
        return $this->sharesOrgContext($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->sharesOrgContext($user, $model);
    }

    /**
     * An Admin may only act on `$model` while impersonating the exact
     * Organization the target user belongs to; a Gestor may only act on
     * users within their own `org_id`. Never trusts anything from the
     * request — both sides of the comparison come from server-resolved
     * state (`$user->org_id` / `session('active_org_id')`).
     */
    protected function sharesOrgContext(User $user, User $model): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            $activeOrgId = session('active_org_id');

            return $activeOrgId && (int) $model->org_id === (int) $activeOrgId;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return $user->org_id && (int) $model->org_id === (int) $user->org_id;
        }

        return false;
    }
}
