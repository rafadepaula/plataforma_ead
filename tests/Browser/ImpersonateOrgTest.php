<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for the "Entrar como" (Impersonate Org)
 * flow from the Organizations index.
 */
class ImpersonateOrgTest extends DuskTestCase
{
    public function test_admin_can_impersonate_and_exit_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Alvo']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$organization->id)
                ->click('@impersonate-'.$organization->id)
                ->waitForLocation('/organizations')
                ->assertSee('Organização Alvo')
                //  leaving the context now redirects to a
                // deterministic destination (`admin.dashboard`) instead
                // of `back()`.
                ->waitFor('@exit-impersonation')
                ->click('@exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->assertSee('Contexto de Organização encerrado.');
        });
    }
}
