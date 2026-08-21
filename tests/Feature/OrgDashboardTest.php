<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Tests\TestCase;

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
        $response->assertSee('Um panorama da plataforma nos últimos 30 dias.');
        $response->assertDontSee('Novo curso');
        $response->assertSee(route('reports.export', ['type' => 'enrollments']), false);
        $response->assertSee(route('reports.export', ['type' => 'certificates']), false);
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
        $response->assertSee('Um panorama da sua organização nos últimos 30 dias.');
        $response->assertSee('Novo curso');
        $response->assertSee(route('courses.create'), false);
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
        $response->assertSee('Novo curso');
    }

    public function test_aluno_cannot_access_the_admin_dashboard(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $response = $this->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_admin_with_no_impersonated_org_sees_organizations_summary_with_correct_counts(): void
    {
        $orgA = Organization::factory()->create();
        $orgWithoutData = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);

        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Organizações');
        $response->assertSee($orgA->name);
        $response->assertSee($orgWithoutData->name);
        $response->assertViewHas('organizationsSummary');

        $summary = $response->viewData('organizationsSummary')->keyBy('id');

        $this->assertSame(1, (int) $summary[$orgA->id]->students_count);
        $this->assertSame(1, (int) $summary[$orgA->id]->courses_count);

        $this->assertSame(0, (int) $summary[$orgWithoutData->id]->students_count);
        $this->assertSame(0, (int) $summary[$orgWithoutData->id]->courses_count);
        $this->assertSame(0, (int) $summary[$orgWithoutData->id]->certificates_count);
    }

    public function test_gestor_never_receives_organizations_summary(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('organizationsSummary', null);
        $response->assertDontSee('organizations-summary-table', false);
    }

    public function test_admin_impersonating_an_org_does_not_receive_organizations_summary(): void
    {
        $org = Organization::factory()->create();

        $this->actingAsAdmin($org);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('organizationsSummary', null);
        $response->assertDontSee('organizations-summary-table', false);
    }

    public function test_dashboard_renders_metric_captions_table_semantics_and_accessible_progress(): void
    {
        $org = Organization::factory()->create();
        Course::factory()->for($org)->published()->count(2)->create();
        $draftCourse = Course::factory()->for($org)->create(['title' => 'Curso em rascunho']);
        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'Ana Lima']);
        $student->assignRole('aluno');
        $draftCourse->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);

        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats): bool {
            return $stats['courses_count'] === 2 && $stats['draft_courses_count'] === 1;
        });
        $response->assertSee('vs. período anterior');
        $response->assertSee('emitidos no total');
        $response->assertSee('média dos cursos');
        $response->assertSee('1 em rascunho');
        $this->assertSame(0, substr_count($response->getContent(), 'Sem dados no período'));
        $response->assertSee('lucide-user', false);
        $response->assertDontSee('lucide-users', false);
        $response->assertSee('aria-label="Matrículas recentes"', false);
        $response->assertSee('Atualizado agora');
        $response->assertSee('aria-label="Progresso de Ana Lima no curso Curso em rascunho: 40%"', false);
        $response->assertDontSee('Ver todas');
    }

    public function test_dashboard_hides_zero_attention_rows_and_renders_the_all_clear_state(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Nada aguardando você');
        $response->assertSee('Você está em dia com as filas desta organização.');
        $response->assertDontSee('Redações aguardando correção');
        $response->assertDontSee('Denúncias no fórum');
        $response->assertDontSee('Certificados emitidos recentemente');
        $response->assertSee('Nenhuma matrícula ainda');
        $response->assertSee('Convide alunos por link e as matrículas aparecem aqui.');
        $response->assertSee('Ver cursos para convidar');
        $response->assertSee(route('courses.index'), false);
        $this->assertSame(4, substr_count($response->getContent(), 'Sem dados no período'));
        $response->assertDontSee('vs. período anterior');
        $response->assertDontSee('emitidos no total');
        $response->assertDontSee('média dos cursos');
        $response->assertDontSee('0 em rascunho');
    }

    public function test_dashboard_attention_rows_link_to_their_real_destinations(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole('aluno');

        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $quiz = Quiz::factory()->for($lesson)->create();
        QuizAttempt::factory()->for($quiz)->for($student)->awaitingManualGrading()->create();

        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $org->id]);
        ForumReport::factory()->for($student, 'reporter')->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
        ]);

        Certificate::factory()->for($course)->for($student)->create();

        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Redações aguardando correção');
        $response->assertSee(route('quiz-attempts.pending'), false);
        $response->assertSee('Denúncias no fórum');
        $response->assertSee(route('forum-moderation.index'), false);
        $response->assertSee('Certificados emitidos recentemente');
        $response->assertSee(route('reports.export', ['type' => 'certificates']), false);
        $response->assertDontSee('Nada aguardando você');
    }

    public function test_dashboard_limits_the_most_completed_courses_ranking_to_three(): void
    {
        $org = Organization::factory()->create();

        foreach (['Curso Alfa', 'Curso Beta', 'Curso Gama', 'Curso Zeta'] as $title) {
            $course = Course::factory()->for($org)->create(['title' => $title]);
            $student = User::factory()->create(['org_id' => $org->id]);
            $student->assignRole('aluno');
            $course->students()->attach($student->id, [
                'enrolled_at' => now(),
                'status' => 'completed',
                'progress_percentage' => 100,
            ]);
        }

        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('mostCompletedCourses', function ($courses): bool {
            return $courses->count() === 3
                && $courses->doesntContain(fn ($course): bool => $course->course_name === 'Curso Zeta');
        });
    }
}
