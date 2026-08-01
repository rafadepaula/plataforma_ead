<?php

namespace Tests;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Log in as a system Admin (`org_id = null`, `admin` role).
     *
     * Pass an Organization to also simulate an active "Impersonate Org"
     * context (SPEC-00 §3) by seeding `session('active_org_id')`.
     */
    protected function actingAsAdmin(?Organization $impersonatedOrg = null): User
    {
        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->actingAs($admin);

        if ($impersonatedOrg) {
            $this->withSession(['active_org_id' => $impersonatedOrg->id]);
        }

        return $admin;
    }

    /**
     * Log in as an org-bound user (`gestor` role by default) tied to the
     * given (or a freshly created) Organization.
     */
    protected function actingAsOrgUser(
        ?Organization $organization = null,
        string $role = 'gestor',
    ): User {
        $organization ??= Organization::factory()->create();

        /** @var User $user */
        $user = User::factory()->create(['org_id' => $organization->id]);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }
}
