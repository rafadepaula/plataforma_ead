<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;

/**
 * Organization CRUD is reserved to `role:admin`. Kept
 * as an explicit Policy (rather than relying solely on route middleware)
 * so authorization is enforced the same way whether it's reached via a
 * web route, a Blade `@can` check, or a future API endpoint.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }
}
