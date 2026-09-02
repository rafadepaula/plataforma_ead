<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * `StudentCourseController@index` ("Meus Cursos"): tab-filtered,
 * multi-org enrollment catalog. Covers the GET `?status=` tab contract,
 * per-card status derivation/CTA resolution, and the edge cases
 * (cancelled exclusion, soft-deleted/unpublished courses not crashing the page).
 */
class StudentCourseControllerTest extends TestCase
{
    private function makeAluno(): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        return $aluno;
    }

    private function publishedCourseWithLesson(Organization $org): Course
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create(['order_index' => 0]);
        Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 0]);

        return $course;
    }

    public function test_index_aggregates_enrollments_across_multiple_organizations(): void
    {
        $aluno = $this->makeAluno();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = $this->publishedCourseWithLesson($orgA);
        $courseB = $this->publishedCourseWithLesson($orgB);

        $aluno->courses()->attach($courseA->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($courseB->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($courseA->title);
        $response->assertSee($courseB->title);
        $response->assertSee($orgA->name);
        $response->assertSee($orgB->name);
    }

    public function test_duplicate_course_titles_across_organizations_show_the_correct_org_per_card_without_n_plus_1(): void
    {
        $aluno = $this->makeAluno();
        $orgA = Organization::factory()->create(['name' => 'Organização A']);
        $orgB = Organization::factory()->create(['name' => 'Organização B']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'title' => 'Curso Duplicado']);
        $moduleA = Module::factory()->for($courseA)->create(['order_index' => 0]);
        Lesson::factory()->for($moduleA)->richText()->create(['is_published' => true, 'order_index' => 0]);

        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'title' => 'Curso Duplicado']);
        $moduleB = Module::factory()->for($courseB)->create(['order_index' => 0]);
        Lesson::factory()->for($moduleB)->richText()->create(['is_published' => true, 'order_index' => 0]);

        $aluno->courses()->attach($courseA->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($courseB->id, ['status' => 'active', 'enrolled_at' => now()]);

        \DB::enableQueryLog();
        $response = $this->actingAs($aluno)->get(route('student.courses.index'));
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($orgA, $orgB) {
            $orgNames = $rows->pluck('organization.name')->sort()->values()->all();

            return $orgNames === [$orgA->name, $orgB->name];
        });

        // One query for the aggregate rows plus a handful of fixed-cost
        // queries (session/auth/etc) — not one extra query per row for
        // `organization`, which `->with('organization')` prevents.
        $this->assertLessThan(20, $queryCount, 'Loading the catalog issued a suspiciously high query count, suggesting an N+1 on `organization`.');
    }

    public function test_em_andamento_tab_is_the_default_and_shows_only_active_enrollments(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $activeCourse = $this->publishedCourseWithLesson($org);
        $completedCourse = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'progress_percentage' => 40, 'enrolled_at' => now()]);
        $aluno->courses()->attach($completedCourse->id, ['status' => 'completed', 'progress_percentage' => 100, 'enrolled_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($activeCourse->title);
        $response->assertDontSee($completedCourse->title);
    }

    public function test_concluidos_tab_filters_to_completed_enrollments_only(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $activeCourse = $this->publishedCourseWithLesson($org);
        $completedCourse = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($completedCourse->id, ['status' => 'completed', 'progress_percentage' => 100, 'enrolled_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'concluidos']));

        $response->assertOk();
        //  scoped to the tab's card: the active course's title
        // legitimately appears page-wide now (sidebar submenu shortcuts
        // list every active enrollment on any student page), so a
        // page-wide `assertDontSee($title)` would false-positive.
        $response->assertDontSee('dusk="course-card-'.$activeCourse->id.'"', false);
        $response->assertSee($completedCourse->title);
    }

    public function test_todos_tab_shows_both_active_and_completed_but_never_cancelled(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $activeCourse = $this->publishedCourseWithLesson($org);
        $completedCourse = $this->publishedCourseWithLesson($org);
        $cancelledCourse = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($completedCourse->id, ['status' => 'completed', 'progress_percentage' => 100, 'enrolled_at' => now(), 'completed_at' => now()]);
        $aluno->courses()->attach($cancelledCourse->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'todos']));

        $response->assertOk();
        $response->assertSee($activeCourse->title);
        $response->assertSee($completedCourse->title);
        $response->assertDontSee($cancelledCourse->title);
    }

    public function test_an_unknown_status_value_falls_back_to_em_andamento(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $activeCourse = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'not-a-real-tab']));

        $response->assertOk();
        $response->assertSee($activeCourse->title);
    }

    public function test_em_andamento_tab_badge_counts_only_active_enrollments(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $activeOne = $this->publishedCourseWithLesson($org);
        $activeTwo = $this->publishedCourseWithLesson($org);
        $completed = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($activeOne->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($activeTwo->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($completed->id, ['status' => 'completed', 'progress_percentage' => 100, 'enrolled_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('tabCounts', fn (array $counts): bool => $counts['em_andamento'] === 2 && $counts['concluidos'] === 1 && $counts['todos'] === 3);
    }

    public function test_empty_state_is_contextual_per_tab_when_no_rows_match(): void
    {
        $aluno = $this->makeAluno();

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'concluidos']));

        $response->assertOk();
        $response->assertSee('no-enrollments', false);
        $response->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
    }

    public function test_a_cancelled_enrollment_never_appears_in_any_tab(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $cancelledCourse = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($cancelledCourse->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        foreach (['em_andamento', 'concluidos', 'todos'] as $tab) {
            $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => $tab]));
            $response->assertOk();
            $response->assertDontSee($cancelledCourse->title);
        }
    }

    public function test_status_derivation_nao_iniciado_shows_start_course_cta(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);
        $firstLesson = $course->firstPublishedLessonFor();

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 0, 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($firstLesson) {
            $row = $rows->first();

            return $row->displayStatus === 'nao_iniciado'
                && $row->ctaHref === route('classroom.lesson', $firstLesson);
        });
    }

    public function test_status_derivation_em_andamento_shows_resume_cta(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 50, 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->first()->displayStatus === 'em_andamento'
            && $rows->first()->ctaLabel === 'Continuar');
    }

    public function test_status_derivation_concluido_wins_over_a_past_expires_at(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, [
            'status' => 'completed',
            'progress_percentage' => 100,
            'enrolled_at' => now()->subMonth(),
            'completed_at' => now()->subDay(),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'concluidos']));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->first()->displayStatus === 'concluido');
    }

    public function test_status_derivation_expirado_when_active_and_past_expires_at(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'progress_percentage' => 30,
            'enrolled_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($course) {
            $row = $rows->first();

            return $row->displayStatus === 'expirado'
                && $row->ctaHref === route('classroom.show', $course)
                && $row->progressPercentage >= 2;
        });
    }

    public function test_expirado_with_zero_real_progress_still_shows_the_2_percent_visual_minimum(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'progress_percentage' => 0,
            'enrolled_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) {
            $row = $rows->first();

            return $row->displayStatus === 'expirado' && $row->progressPercentage === 2;
        });
    }

    public function test_concluido_status_offers_certificate_download_when_certificate_is_issued(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, [
            'status' => 'completed',
            'progress_percentage' => 100,
            'enrolled_at' => now()->subMonth(),
            'completed_at' => now(),
        ]);

        $certificate = Certificate::factory()->for($course)->for($aluno)->create();

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'concluidos']));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->first()->ctaHref === route('certificates.download', $certificate));
    }

    public function test_concluido_status_degrades_gracefully_when_certificate_has_not_been_issued_yet(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, [
            'status' => 'completed',
            'progress_percentage' => 100,
            'enrolled_at' => now()->subMonth(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index', ['status' => 'concluidos']));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->first()->ctaHref === null);
    }

    public function test_a_course_with_no_published_lessons_does_not_crash_and_degrades_its_cta(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 0, 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) {
            $row = $rows->first();

            return $row->ctaHref === null && $row->lessonsCount === 0;
        });
    }

    public function test_a_soft_deleted_course_does_not_crash_the_catalog_and_is_excluded(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $course->delete();

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertDontSee($course->title);
    }

    public function test_an_unpublished_course_with_an_active_enrollment_still_shows_read_only(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = $this->publishedCourseWithLesson($org);
        $course->update(['is_published' => false]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($course->title);
    }

    public function test_gestor_cannot_access_student_courses_index(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('student.courses.index'));

        $response->assertForbidden();
    }

    public function test_resume_lesson_progress_correctly_resolves_the_continue_cta(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create(['order_index' => 0]);
        $lessonOne = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 0]);
        $lessonTwo = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 1]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 50, 'enrolled_at' => now()]);

        LessonProgress::create([
            'user_id' => $aluno->id,
            'lesson_id' => $lessonOne->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->first()->ctaHref === route('classroom.lesson', $lessonTwo));
    }
}
