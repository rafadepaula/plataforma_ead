<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrgContext;
use Tests\TestCase;

class HostResolutionTest extends TestCase
{
    public function test_host_mapeado_resolve_a_org_no_contexto(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);

        $this->onHost('portal.acme.test')->get('/login');

        $context = app(OrgContext::class);
        $this->assertTrue($context->organization->is($organization));
        $this->assertTrue($context->orgIsActive);
        $this->assertSame($organization->id, $context->orgId());
    }

    public function test_host_e_resolvido_case_insensitive(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test']);

        $this->onHost('PORTAL.ACME.TEST')->get('/login');

        $this->assertFalse(app(OrgContext::class)->isStateZero());
    }

    public function test_porta_do_host_e_ignorada_na_resolucao(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);

        $this->onHost('portal.acme.test:9999')->get('/login');

        $this->assertTrue(app(OrgContext::class)->organization->is($organization));
    }

    public function test_host_desconhecido_e_estado_zero(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test']);

        $this->onHost('desconhecido.test')->get('/login');

        $context = app(OrgContext::class);
        $this->assertTrue($context->isStateZero());
        $this->assertNull($context->orgId());
        $this->assertFalse($context->orgIsActive);
    }

    public function test_org_sem_host_resolvida_por_nenhum_host(): void
    {
        Organization::factory()->create(['host' => null]);

        $this->get('/login');

        $this->assertTrue(app(OrgContext::class)->isStateZero());
    }

    public function test_org_soft_deletada_nao_resolve(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test'])->delete();

        $this->onHost('portal.acme.test')->get('/login');

        $this->assertTrue(app(OrgContext::class)->isStateZero());
    }

    public function test_visitante_em_estado_zero_e_redirecionado_ao_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_nao_admin_autenticado_em_estado_zero_e_deslogado(): void
    {
        $user = User::factory()->aluno()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_org_inativa_desloga_nao_admin_e_bloqueia_rotas_de_visitante(): void
    {
        Organization::factory()->inactive()->create(['host' => 'portal.acme.test']);

        // Guest em rota não-pública da org inativa volta pra landing.
        $this->onHost('portal.acme.test')->get('/login')->assertRedirect('/');

        // Autenticado não-admin é deslogado.
        $user = User::factory()->aluno()->create();

        $this->actingAs($user)
            ->onHost('portal.acme.test')
            ->get('/')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_admin_navega_livre_em_estado_zero(): void
    {
        $this->actingAsAdmin();

        $this->get(route('organizations.index'))->assertOk();
    }
}
