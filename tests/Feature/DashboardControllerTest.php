<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_aluno_receives_forbidden_response(): void
    {
        $org = Organization::factory()->create();
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole('aluno');

        $this->actingAs($student);

        $response = $this->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_gestor_sees_view_with_period_and_scoped_stats(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole('gestor');

        $course = Course::factory()->for($org)->create(['title' => 'Curso de Teste', 'is_published' => true]);

        $this->actingAs($gestor);

        $response = $this->get(route('admin.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('period', '7d');
        $response->assertViewHas('stats');
        $response->assertViewHas('recentEnrollments');
        $response->assertViewHas('isGlobalAdminView', false);
        $response->assertViewHas('canCreateCourse', true);
    }

    public function test_invalid_period_safely_falls_back_to_30d(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole('gestor');

        $this->actingAs($gestor);

        $response = $this->get(route('admin.dashboard', ['period' => 'invalid_value']));

        $response->assertOk();
        $response->assertViewHas('period', '30d');
    }

    public function test_ajax_request_returns_json_payload_with_stats_and_recent_enrollments(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole('gestor');

        $course = Course::factory()->for($org)->create(['is_published' => true]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole('aluno');

        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 50,
        ]);

        $this->actingAs($gestor);

        $response = $this->getJson(route('admin.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => [
                'active_students',
                'certificates_issued',
                'completion_rate',
                'courses_count',
                'draft_courses_count',
                'active_students_delta',
                'certificates_issued_delta',
            ],
            'recentEnrollments',
            'period',
        ]);
        $response->assertJson([
            'period' => '7d',
        ]);
    }
}
