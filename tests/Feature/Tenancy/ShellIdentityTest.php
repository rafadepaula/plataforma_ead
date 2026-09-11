<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use Tests\TestCase;

class ShellIdentityTest extends TestCase
{
    public function test_login_page_mostra_o_nome_da_org_do_host(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'name' => 'Acme Capacitações']);

        $this->onHost('portal.acme.test')
            ->get('/login')
            ->assertOk()
            ->assertSee('Acme Capacitações');
    }

    public function test_login_page_no_estado_zero_mostra_system_name(): void
    {
        $this->onHost('127.0.0.1')
            ->get('/login')
            ->assertOk()
            ->assertSee(config('app.name', 'Plataforma EAD'));
    }

    public function test_admin_no_host_de_uma_org_ve_o_system_name(): void
    {
        // spec 9: o Admin global não é "de" nenhum portal — o shell dele
        // é a plataforma, mesmo navegando no host de uma org
        Organization::factory()->create(['host' => 'portal.acme.test', 'name' => 'Acme Capacitações']);
        $this->actingAsAdmin();

        $this->onHost('portal.acme.test')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(config('app.name', 'Plataforma EAD'));
    }

    public function test_tela_logada_mostra_a_marca_da_org_do_host(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test', 'name' => 'Acme Capacitações']);
        $this->actingAsOrgUser(Organization::query()->where('host', 'portal.acme.test')->firstOrFail());

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Acme Capacitações')
            ->assertSee('topbar-org-name', false);
    }
}
