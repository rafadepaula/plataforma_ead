<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_host_com_landing_view_renderiza_o_blade_da_org(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'landing_view' => 'ligacerto']);

        $this->onHost('portal.acme.test')
            ->get('/')
            ->assertOk()
            ->assertSee('LigaCerto', false);
    }

    public function test_orgs_diferentes_renderizam_blades_diferentes(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'landing_view' => 'ligacerto']);
        Organization::factory()->create(['host' => 'portal.bsb.test', 'landing_view' => 'informatica']);

        $this->onHost('portal.bsb.test')
            ->get('/')
            ->assertOk()
            ->assertSee('Informática', false);
    }

    public function test_org_sem_landing_view_redireciona_ao_login(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'landing_view' => null]);

        $this->onHost('portal.acme.test')
            ->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_org_com_landing_view_inexistente_redireciona_ao_login(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'landing_view' => 'org-que-nao-existe']);

        $this->onHost('portal.acme.test')
            ->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_estado_zero_redireciona_ao_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_org_inativa_ainda_renderiza_a_landing(): void
    {
        Organization::factory()->inactive()->create(['host' => 'portal.acme.test', 'landing_view' => 'ligacerto']);

        $this->onHost('portal.acme.test')
            ->get('/')
            ->assertOk()
            ->assertSee('LigaCerto', false);
    }
}
