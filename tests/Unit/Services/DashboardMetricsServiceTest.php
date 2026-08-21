<?php

namespace Tests\Unit\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `DashboardMetricsService` computes the dashboard stat shape.
 * `Certificate` and
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

    public function test_course_counts_separate_published_and_drafts_with_the_same_gestor_org_scope(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        Course::factory()->for($org)->published()->count(3)->create();
        Course::factory()->for($org)->count(2)->create();
        Course::factory()->for($otherOrg)->published()->count(5)->create();
        Course::factory()->for($otherOrg)->count(4)->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->actingAs($gestor);

        $stats = $this->service->getStats($org->id);

        $this->assertSame(3, $stats['courses_count']);
        $this->assertSame(2, $stats['draft_courses_count']);
    }

    public function test_an_admin_with_no_active_org_sees_globally_unfiltered_stats(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $courseA = Course::factory()->for($orgA)->published()->create();
        $courseB = Course::factory()->for($orgB)->published()->create();
        Course::factory()->for($orgA)->create();
        Course::factory()->for($orgB)->create();

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
        $this->assertSame(2, $stats['draft_courses_count']);
    }

    public function test_recent_enrollments_returns_the_latest_rows_scoped_to_the_given_org_with_the_expected_shape(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create(['title' => 'NR12 — Segurança em Máquinas']);
        $otherCourse = Course::factory()->for($otherOrg)->create(['title' => 'Outro Curso']);

        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'João da Silva Pereira']);
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
        $this->assertSame('João da Silva Pereira', $enrollment->student_name);
        $this->assertSame('JP', $enrollment->student_initials);
        $this->assertSame('NR12 — Segurança em Máquinas', $enrollment->course_name);
        $this->assertSame('Concluída', $enrollment->status_label);
        $this->assertSame('success', $enrollment->status_badge_variant);
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

    public function test_recent_enrollments_includes_the_students_email_and_progress_percentage(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        $student = User::factory()->create(['org_id' => $org->id, 'email' => 'joao@example.com']);
        $student->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = $this->service->recentEnrollments($org->id)->first();

        $this->assertSame('joao@example.com', $enrollment->student_email);
        $this->assertSame(55, $enrollment->progress_percentage);
        $this->assertSame('Em andamento', $enrollment->status_label);
        $this->assertSame('info', $enrollment->status_badge_variant);
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

        $this->assertSame('Cancelada', $enrollment->status_label);
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

    public function test_get_stats_returns_null_deltas_when_there_is_no_activity_before_the_period(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $this->enrollActiveStudent($course);
        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()]);

        $stats = $this->service->getStats($org->id, '30d');

        $this->assertNull($stats['active_students_delta']);
        $this->assertNull($stats['certificates_issued_delta']);
    }

    public function test_get_stats_computes_the_active_students_delta_between_the_current_and_previous_period(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        $baseline = $this->enrollActiveStudent($course);
        DB::table('course_user')->where('user_id', $baseline->id)->update(['created_at' => now()->subDays(40)]);

        $this->enrollActiveStudent($course);

        $stats = $this->service->getStats($org->id, '30d');

        $this->assertSame('+100,0%', $stats['active_students_delta']);
    }

    public function test_get_stats_computes_the_certificates_issued_delta_between_the_current_and_previous_period(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()->subDays(40)]);
        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()->subDays(2)]);
        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()->subDays(2)]);

        $stats = $this->service->getStats($org->id, '30d');

        $this->assertSame('+200,0%', $stats['certificates_issued_delta']);
    }

    public function test_attention_counts_counts_pending_essays_forum_reports_and_recent_certificates_scoped_to_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $course = Course::factory()->for($org)->create();
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $quiz = Quiz::factory()->for($lesson)->create();

        QuizAttempt::factory()->for($quiz)->for(User::factory())->awaitingManualGrading()->create();
        QuizAttempt::factory()->for($quiz)->for(User::factory())->graded()->create();

        $topic = ForumTopic::factory()->for($course)->for(User::factory())->create(['org_id' => $org->id]);
        ForumReport::factory()->for(User::factory(), 'reporter')->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
        ]);
        ForumReport::factory()->for(User::factory(), 'reporter')->dismissed()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
        ]);

        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()->subDays(2)]);
        Certificate::factory()->for($course)->for(User::factory())->create(['issued_at' => now()->subDays(20)]);

        $otherCourse = Course::factory()->for($otherOrg)->create();
        $otherModule = Module::factory()->for($otherCourse)->create();
        $otherLesson = Lesson::factory()->for($otherModule)->create();
        $otherQuiz = Quiz::factory()->for($otherLesson)->create();
        QuizAttempt::factory()->for($otherQuiz)->for(User::factory())->awaitingManualGrading()->create();

        $counts = $this->service->attentionCounts($org->id);

        $this->assertSame(1, $counts['pending_essays']);
        $this->assertSame(1, $counts['forum_reports']);
        $this->assertSame(1, $counts['certificates_ready']);
    }

    public function test_attention_counts_includes_pending_reports_on_forum_replies(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $topic = ForumTopic::factory()->for($course)->for(User::factory())->create(['org_id' => $org->id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for(User::factory())->create();

        ForumReport::factory()->for(User::factory(), 'reporter')->create([
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
        ]);

        $counts = $this->service->attentionCounts($org->id);

        $this->assertSame(1, $counts['forum_reports']);
    }

    public function test_attention_counts_for_a_global_admin_view_sees_all_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        foreach ([$orgA, $orgB] as $org) {
            $course = Course::factory()->for($org)->create();
            $module = Module::factory()->for($course)->create();
            $lesson = Lesson::factory()->for($module)->create();
            $quiz = Quiz::factory()->for($lesson)->create();
            QuizAttempt::factory()->for($quiz)->for(User::factory())->awaitingManualGrading()->create();
        }

        $counts = $this->service->attentionCounts(null);

        $this->assertSame(2, $counts['pending_essays']);
    }

    public function test_most_completed_courses_ranks_by_completed_enrollments_desc_scoped_to_org(): void
    {
        $org = Organization::factory()->create();
        $courseA = Course::factory()->for($org)->create(['title' => 'Curso A']);
        $courseB = Course::factory()->for($org)->create(['title' => 'Curso B']);
        $otherOrg = Organization::factory()->create();
        $courseC = Course::factory()->for($otherOrg)->create(['title' => 'Curso C']);

        foreach (range(1, 3) as $i) {
            $student = User::factory()->create(['org_id' => $org->id]);
            $student->courses()->attach($courseA->id, ['status' => 'completed', 'enrolled_at' => now()]);
        }

        $studentB = User::factory()->create(['org_id' => $org->id]);
        $studentB->courses()->attach($courseB->id, ['status' => 'completed', 'enrolled_at' => now()]);

        $otherStudent = User::factory()->create(['org_id' => $otherOrg->id]);
        $otherStudent->courses()->attach($courseC->id, ['status' => 'completed', 'enrolled_at' => now()]);

        $ranking = $this->service->mostCompletedCourses($org->id);

        $this->assertCount(2, $ranking);
        $this->assertSame('Curso A', $ranking->first()->course_name);
        $this->assertSame(3, $ranking->first()->completions);
        $this->assertSame(100, $ranking->first()->percentage);
        $this->assertSame(1, $ranking->last()->completions);
        $this->assertSame(33, $ranking->last()->percentage);
    }

    public function test_most_completed_courses_respects_the_limit(): void
    {
        $org = Organization::factory()->create();

        foreach (range(1, 3) as $i) {
            $course = Course::factory()->for($org)->create();
            $student = User::factory()->create(['org_id' => $org->id]);
            $student->courses()->attach($course->id, ['status' => 'completed', 'enrolled_at' => now()]);
        }

        $ranking = $this->service->mostCompletedCourses($org->id, 2);

        $this->assertCount(2, $ranking);
    }

    public function test_most_completed_courses_defaults_to_three_rows(): void
    {
        $org = Organization::factory()->create();

        foreach (range(1, 4) as $i) {
            $course = Course::factory()->for($org)->create(['title' => "Curso {$i}"]);

            foreach (range(1, $i) as $studentNumber) {
                $student = User::factory()->create(['org_id' => $org->id]);
                $student->courses()->attach($course->id, ['status' => 'completed', 'enrolled_at' => now()]);
            }
        }

        $ranking = $this->service->mostCompletedCourses($org->id);

        $this->assertCount(3, $ranking);
        $this->assertSame(['Curso 4', 'Curso 3', 'Curso 2'], $ranking->pluck('course_name')->all());
        $this->assertSame([100, 75, 50], $ranking->pluck('percentage')->all());
    }
}
