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
                // UX-002 §4.4 — leaving the context now redirects to a
                // deterministic destination (`admin.dashboard`) instead
                // of `back()`.
                ->waitFor('@exit-impersonation')
                ->click('@exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->assertSee('Contexto de Organização encerrado.');
        });
    }

    public function test_an_admin_without_an_impersonated_org_cannot_create_a_course(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('courses.create'))
                ->type('title', 'Curso Sem Organização')
                ->type('workload_hours', '40')
                ->click('@course-submit')
                ->waitForText('Selecione uma Organização ativa antes de continuar.');
        });

        $this->assertDatabaseCount('courses', 0);
    }
}
