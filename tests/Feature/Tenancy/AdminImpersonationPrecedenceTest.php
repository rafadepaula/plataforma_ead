<?php

namespace Tests\Feature\Tenancy;

use App\Models\Course;
use App\Models\Organization;
use Tests\TestCase;

class AdminImpersonationPrecedenceTest extends TestCase
{
    public function test_admin_impersonando_org_de_outro_host_cria_na_org_impersonada(): void
    {
        $hostOrg = Organization::factory()->create(['host' => 'portal.acme.test']);
        $targetOrg = Organization::factory()->create(['host' => 'portal.bsb.test']);

        $this->actingAsAdmin($targetOrg);

        // Admin está no portal do hostOrg, mas impersona targetOrg.
        $this->onHost($hostOrg->host)
            ->post('/courses', [
                'title' => 'Curso da Org B',
                'description' => 'Criado via impersonação.',
                'workload_hours' => 10,
                'is_published' => false,
            ]);

        $course = Course::query()->withoutGlobalScopes()->where('title', 'Curso da Org B')->firstOrFail();

        $this->assertSame($targetOrg->id, $course->org_id);
        $this->assertNotSame($hostOrg->id, $course->org_id);
    }

    public function test_admin_sem_impersonacao_nao_herdar_a_org_do_host_ao_criar(): void
    {
        Organization::factory()->create(['host' => 'portal.acme.test']);

        $this->actingAsAdmin();

        $this->onHost('portal.acme.test')
            ->from('/courses')
            ->post('/courses', [
                'title' => 'Curso Órfão',
                'description' => 'Sem contexto.',
                'workload_hours' => 10,
                'is_published' => false,
            ])
            ->assertSessionHas('error');

        $this->assertNull(Course::query()->withoutGlobalScopes()->where('title', 'Curso Órfão')->first());
    }
}
