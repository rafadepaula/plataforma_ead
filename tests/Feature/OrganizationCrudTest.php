<?php

namespace Tests\Feature;

use App\Models\Organization;
use Tests\TestCase;

class OrganizationCrudTest extends TestCase
{
    public function test_admin_cadastra_org_com_host_e_landing_view(): void
    {
        $this->actingAsAdmin();

        $this->post('/organizations', [
            'name' => 'Acme Capacitações',
            'host' => 'portal.acme.test',
            'landing_view' => 'ligacerto',
            'status' => 'active',
        ]);

        $organization = Organization::query()->where('host', 'portal.acme.test')->firstOrFail();

        $this->assertSame('ligacerto', $organization->landing_view);
    }

    public function test_host_duplicado_e_rejeitado(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test']);
        $this->actingAsAdmin();

        $this->from('/organizations/create')
            ->post('/organizations', [
                'name' => 'Outra Org',
                'host' => 'portal.acme.test',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('host');
    }

    public function test_host_com_formato_invalido_e_rejeitado(): void
    {
        $this->actingAsAdmin();

        $this->from('/organizations/create')
            ->post('/organizations', [
                'name' => 'Org Inválida',
                'host' => 'Portal com espaço.com',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('host');
    }

    public function test_update_mantem_host_da_propria_org(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);
        $this->actingAsAdmin();

        $this->put("/organizations/{$organization->id}", [
            'name' => $organization->name,
            'host' => 'portal.acme.test',
            'landing_view' => 'informatica',
            'status' => 'active',
        ]);

        $this->assertSame('informatica', $organization->fresh()->landing_view);
    }
}
