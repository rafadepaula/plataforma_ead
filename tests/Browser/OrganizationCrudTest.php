<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-04 §2 / RF23 — E2E coverage for the Organization CRUD screens.
 */
class OrganizationCrudTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_can_create_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.create'))
                ->waitFor('@organization-form')
                ->type('name', 'Instituto Dusk')
                ->press('Criar Organização')
                ->waitForLocation('/organizations')
                ->assertSee('Instituto Dusk')
                ->assertSee('Organização criada com sucesso.');
        });
    }

    public function test_admin_can_edit_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Nome Original']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@edit-organization-'.$organization->id)
                ->click('@edit-organization-'.$organization->id)
                ->waitFor('@organization-form')
                ->clear('name')
                ->type('name', 'Nome Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/organizations')
                ->assertSee('Nome Editado');
        });
    }

    public function test_admin_can_soft_delete_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Removível']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$organization->id)
                ->click('@delete-organization-'.$organization->id)
                ->waitForLocation('/organizations')
                ->assertDontSee('Organização Removível');
        });

        $this->assertSoftDeleted($organization);
    }

    public function test_gestor_cannot_reach_the_organizations_index(): void
    {
        $gestor = User::factory()->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('organizations.index'))
                ->assertSee('403');
        });
    }
}
