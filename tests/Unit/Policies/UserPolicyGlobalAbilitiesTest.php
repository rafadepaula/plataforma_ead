<?php

namespace Tests\Unit\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use App\Policies\UserPolicy;
use Tests\TestCase;

/**
 * the cross-org abilities (`viewAnyGlobal`/`viewGlobal`/
 * `updateGlobal`/`deleteGlobal`) added to `UserPolicy` for the global
 * Admin user-management screen. Asserted directly against the Policy
 * class, and paired with a regression guard that the existing
 * `sharesOrgContext()`-driven abilities used by the operational
 * `users.*` screen are untouched.
 */
class UserPolicyGlobalAbilitiesTest extends TestCase
{
    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    public function test_only_admin_passes_view_any_global(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->assertTrue($this->policy->viewAnyGlobal($admin));
        $this->assertFalse($this->policy->viewAnyGlobal($gestor));
        $this->assertFalse($this->policy->viewAnyGlobal($aluno));
    }

    public function test_admin_can_view_and_update_a_user_from_any_org_without_impersonation(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::ALUNO->value);

        // No `session('active_org_id')` set — this is exactly what the
        // org-scoped `sharesOrgContext()` abilities deny; the *Global
        // abilities must not depend on it at all.
        $this->assertNull(session('active_org_id'));
        $this->assertTrue($this->policy->viewGlobal($admin, $target));
        $this->assertTrue($this->policy->updateGlobal($admin, $target));
    }

    public function test_gestor_and_aluno_fail_every_global_ability(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->assertFalse($this->policy->viewGlobal($gestor, $target));
        $this->assertFalse($this->policy->updateGlobal($gestor, $target));
        $this->assertFalse($this->policy->deleteGlobal($gestor, $target));
    }

    public function test_admin_cannot_delete_their_own_account_via_delete_global(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertFalse($this->policy->deleteGlobal($admin, $admin));
    }

    public function test_admin_can_delete_another_users_account_via_delete_global(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::GESTOR->value);

        $this->assertTrue($this->policy->deleteGlobal($admin, $target));
    }

    /**
     * Regression guard (RN — "não relaxar UserPolicy"): the *Global
     * abilities must not have changed the behaviour of the pre-existing
     * `sharesOrgContext()`-driven abilities used by the operational
     * `users.*` screen.
     */
    public function test_operational_abilities_are_unaffected_by_the_new_global_abilities(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::ALUNO->value);

        // Admin has no active impersonation: the operational ability
        // must still deny, exactly as before this feature existed.
        $this->assertFalse($this->policy->view($admin, $target));
        $this->assertFalse($this->policy->update($admin, $target));
        $this->assertFalse($this->policy->delete($admin, $target));

        session(['active_org_id' => $org->id]);
        $this->assertTrue($this->policy->view($admin, $target));
        $this->assertTrue($this->policy->update($admin, $target));
        $this->assertTrue($this->policy->delete($admin, $target));
    }
}
