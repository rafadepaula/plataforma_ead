<?php

namespace Tests\Unit\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Tests\TestCase;

/**
 * SPEC-12 §4 — `DashboardMetricsService` computes the mockup's exact stat
 * shape (`spec/docs/mockups/07-dashboard-admin.md` §4). `Certificate` and
 * `course_user` carry no `OrgScope` (cascade-inherited through `Course`),
 * so every stat here is filtered by an explicitly-passed `$orgId` via a
 * join through `courses.org_id` — never by relying on an ambient
 * `Auth::user()`/`session('active_org_id')` context inside the service
 * itself. `courses_count` is the one exception: it relies on `Course`'s
 * own `OrgScope`, so those assertions act as the Organization's Gestor (or
 * an impersonating Admin) to exercise that trait's normal resolution.
 */
class DashboardMetricsServiceTest extends TestCase
{
    private DashboardMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardMetricsService;
    }

    private function enrollActiveStudent(Course $course): User
    {
        $student = User::factory()->create(['org_id' => $course->org_id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $student->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 0,
        ]);

        return $student;
    }

    public function test_active_students_counts_only_alunos_with_an_active_enrollment_in_the_given_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        $this->enrollActiveStudent($course);
        $this->enrollActiveStudent($course);
        $this->enrollActiveStudent($otherCourse);

        $cancelledStudent = User::factory()->create(['org_id' => $org->id]);
        $cancelledStudent->assignRole(RolesEnum::ALUNO->value);
        $cancelledStudent->courses()->attach($course->id, [
            'status' => 'cancelled',
            'enrolled_at' => now(),
        ]);

        $stats = $this->service->getStats($org->id);

        $this->assertSame(2, $stats['active_students']);
    }

    public function test_active_students_counts_a_student_only_once_across_multiple_active_enrollments(): void
    {
        $org = Organization::factory()->create();
        $courseA = Course::factory()->for($org)->create();
        $courseB = Course::factory()->for($org)->create();

        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $student->courses()->attach($courseA->id, ['status' => 'active', 'enrolled_at' => now()]);
        $student->courses()->attach($courseB->id, ['status' => 'active', 'enrolled_at' => now()]);

        $stats = $this->service->getStats($org->id);

        $this->assertSame(1, $stats['active_students']);
    }

    public function test_certificates_issued_counts_only_non_revoked_certificates_in_the_given_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        Certificate::factory()->for($course)->for(User::factory())->create();
        Certificate::factory()->for($course)->for(User::factory())->revoked()->create();
        Certificate::factory()->for($otherCourse)->for(User::factory())->create();

        $stats = $this->service->getStats($org->id);

        $this->assertSame(1, $stats['certificates_issued']);
    }

    public function test_completion_rate_is_the_average_progress_percentage_for_the_given_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        $studentA = User::factory()->create(['org_id' => $org->id]);
        $studentA->courses()->attach($course->id, ['status' => 'completed', 'enrolled_at' => now(), 'progress_percentage' => 100]);

        $studentB = User::factory()->create(['org_id' => $org->id]);
        $studentB->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 40]);

        $outsider = User::factory()->create(['org_id' => $otherOrg->id]);
        $outsider->courses()->attach($otherCourse->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 0]);

        $stats = $this->service->getStats($org->id);

        $this->assertSame(70, $stats['completion_rate']);
    }

    public function test_completion_rate_is_zero_when_there_are_no_enrollments(): void
    {
        $org = Organization::factory()->create();

        $stats = $this->service->getStats($org->id);

        $this->assertSame(0, $stats['completion_rate']);
    }

    public function test_courses_count_relies_on_the_gestors_own_org_scope(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        Course::factory()->for($org)->count(3)->create();
        Course::factory()->for($otherOrg)->count(5)->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->actingAs($gestor);

        $stats = $this->service->getStats($org->id);

        $this->assertSame(3, $stats['courses_count']);
    }

    public function test_an_admin_with_no_active_org_sees_globally_unfiltered_stats(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $this->enrollActiveStudent($courseA);
        $this->enrollActiveStudent($courseB);
        Certificate::factory()->for($courseA)->for(User::factory())->create();
        Certificate::factory()->for($courseB)->for(User::factory())->create();

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin);

        $stats = $this->service->getStats(null);

        $this->assertSame(2, $stats['active_students']);
        $this->assertSame(2, $stats['certificates_issued']);
        $this->assertSame(2, $stats['courses_count']);
    }

    public function test_recent_enrollments_returns_the_latest_rows_scoped_to_the_given_org_with_the_expected_shape(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create(['title' => 'NR12 — Segurança em Máquinas']);
        $otherCourse = Course::factory()->for($otherOrg)->create(['title' => 'Outro Curso']);

        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'João Pereira']);
        $student->courses()->attach($course->id, [
            'status' => 'completed',
            'enrolled_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $outsider = User::factory()->create(['org_id' => $otherOrg->id, 'name' => 'Fora da Org']);
        $outsider->courses()->attach($otherCourse->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recentEnrollments = $this->service->recentEnrollments($org->id);

        $this->assertCount(1, $recentEnrollments);
        $enrollment = $recentEnrollments->first();
        $this->assertSame('João Pereira', $enrollment->student_name);
        $this->assertSame('NR12 — Segurança em Máquinas', $enrollment->course_name);
        $this->assertSame('Concluído', $enrollment->status_label);
        $this->assertSame('neutral', $enrollment->status_badge_variant);
    }

    public function test_recent_enrollments_respects_the_given_limit_and_latest_first_order(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        foreach (range(1, 3) as $i) {
            $student = User::factory()->create(['org_id' => $org->id, 'name' => "Student {$i}"]);
            $student->courses()->attach($course->id, [
                'status' => 'active',
                'enrolled_at' => now(),
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        $recentEnrollments = $this->service->recentEnrollments($org->id, 2);

        $this->assertCount(2, $recentEnrollments);
        $this->assertSame('Student 3', $recentEnrollments->first()->student_name);
    }

    public function test_recent_enrollments_labels_a_cancelled_enrollment(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'Enrollment Cancelada']);
        $student->courses()->attach($course->id, [
            'status' => 'cancelled',
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = $this->service->recentEnrollments($org->id)->first();

        $this->assertSame('Cancelado', $enrollment->status_label);
        $this->assertSame('accent-2', $enrollment->status_badge_variant);
    }

    public function test_organizations_summary_counts_active_alunos_courses_and_non_revoked_certificates_per_org(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);
        $orgB = Organization::factory()->create(['name' => 'Organização B']);

        $courseA = Course::factory()->for($orgA)->create();
        Course::factory()->for($orgB)->count(2)->create();

        $activeAluno = User::factory()->create(['org_id' => $orgA->id, 'status' => 'active']);
        $activeAluno->assignRole(RolesEnum::ALUNO->value);

        $inactiveAluno = User::factory()->create(['org_id' => $orgA->id, 'status' => 'inactive']);
        $inactiveAluno->assignRole(RolesEnum::ALUNO->value);

        $gestor = User::factory()->create(['org_id' => $orgA->id, 'status' => 'active']);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        Certificate::factory()->for($courseA)->for(User::factory())->create();
        Certificate::factory()->for($courseA)->for(User::factory())->revoked()->create();

        $summary = $this->service->organizationsSummary();

        $rowA = $summary->firstWhere('id', $orgA->id);
        $rowB = $summary->firstWhere('id', $orgB->id);

        $this->assertSame('Organização A', $rowA->name);
        $this->assertSame(1, $rowA->students_count);
        $this->assertSame(1, $rowA->courses_count);
        $this->assertSame(1, $rowA->certificates_count);

        $this->assertSame('Organização B', $rowB->name);
        $this->assertSame(0, $rowB->students_count);
        $this->assertSame(2, $rowB->courses_count);
        $this->assertSame(0, $rowB->certificates_count);
    }

    public function test_organizations_summary_bypasses_courses_org_scope_regardless_of_the_acting_user(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        Course::factory()->for($orgA)->count(2)->create();
        Course::factory()->for($orgB)->count(3)->create();

        $gestor = User::factory()->create(['org_id' => $orgA->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestor);

        $summary = $this->service->organizationsSummary();

        $this->assertSame(2, $summary->firstWhere('id', $orgA->id)->courses_count);
        $this->assertSame(3, $summary->firstWhere('id', $orgB->id)->courses_count);
    }

    public function test_organizations_summary_zero_fills_an_organization_with_no_related_data(): void
    {
        $org = Organization::factory()->create();

        $summary = $this->service->organizationsSummary();

        $row = $summary->firstWhere('id', $org->id);

        $this->assertSame(0, $row->students_count);
        $this->assertSame(0, $row->courses_count);
        $this->assertSame(0, $row->certificates_count);
    }

    public function test_organizations_summary_excludes_soft_deleted_organizations(): void
    {
        $org = Organization::factory()->create();
        $org->delete();

        $summary = $this->service->organizationsSummary();

        $this->assertNull($summary->firstWhere('id', $org->id));
    }
}
