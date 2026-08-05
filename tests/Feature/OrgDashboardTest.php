<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-12 — `GET /admin/dashboard` (`admin.dashboard`) must render
 * globally-aggregated KPIs/recentEnrollments for a system Admin with no
 * active "Impersonate Org" session, the scoped KPIs/recentEnrollments of
 * a single Organization once the Admin impersonates it, and only the
 * acting Gestor's own `org_id`'s KPIs/recentEnrollments for a Gestor —
 * never another Organization's data (RN — multi-tenant dashboard scoping).
 */
class OrgDashboardTest extends TestCase
{
    public function test_admin_with_no_impersonated_org_sees_global_kpis_and_all_recent_enrollments(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Alunos ativos');
        $response->assertSee('Matrículas recentes');
        $response->assertSee($studentA->name);
        $response->assertSee($studentB->name);
    }

    public function test_admin_impersonating_an_org_sees_only_that_orgs_scoped_dashboard(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsAdmin($orgA);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($studentA->name);
        $response->assertDontSee($studentB->name);
    }

    public function test_gestor_sees_only_their_own_orgs_kpis_and_recent_enrollments(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsOrgUser($orgA, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($studentA->name);
        $response->assertDontSee($studentB->name);
    }

    public function test_aluno_cannot_access_the_admin_dashboard(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $response = $this->get(route('admin.dashboard'));

        $response->assertForbidden();
    }
}
