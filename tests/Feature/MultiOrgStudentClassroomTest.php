<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Multi-org Aluno classroom access, enrollment gating, module/lesson visibility,
 * progress percentage calculations, next-lesson resolution, and certification presence.
 */
class MultiOrgStudentClassroomTest extends TestCase
{
    private function makeAluno(): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        return $aluno;
    }

    public function test_student_courses_index_groups_enrollments_by_organization(): void
    {
        $aluno = $this->makeAluno();

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id]);

        $aluno->courses()->attach($courseA->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($courseB->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($courseA->title);
        $response->assertSee($courseB->title);
    }

    public function test_student_courses_index_excludes_cancelled_enrollments(): void
    {
        $aluno = $this->makeAluno();

        $org = Organization::factory()->create();
        $activeCourse = Course::factory()->create(['org_id' => $org->id]);
        $cancelledCourse = Course::factory()->create(['org_id' => $org->id]);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($cancelledCourse->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($activeCourse->title);
        $response->assertDontSee($cancelledCourse->title);
    }

    public function test_gestor_cannot_access_student_courses_index(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('student.courses.index'));

        $response->assertForbidden();
    }

    public function test_enrolled_active_student_can_view_the_classroom(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 0, 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee($lesson->title);
        $response->assertViewHas('course', fn (Course $c): bool => $c->id === $course->id);
        $response->assertViewHas('progressPercentage', 0);
    }

    public function test_enrolled_completed_student_can_view_the_classroom(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, [
            'status' => 'completed',
            'progress_percentage' => 100,
            'enrolled_at' => now()->subDays(10),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee($lesson->title);
        $response->assertViewHas('progressPercentage', 100);
    }

    public function test_student_without_enrollment_is_sent_back_to_the_catalog(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        Module::factory()->for($course)->create();

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertRedirect(route('student.courses.index'));
        $response->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_student_with_cancelled_enrollment_is_sent_back_to_the_catalog(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        Module::factory()->for($course)->create();

        $aluno->courses()->attach($course->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertRedirect(route('student.courses.index'));
        $response->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_classroom_view_only_receives_published_lessons_and_modules(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        $publishedLesson = Lesson::factory()->for($module)->richText()->create([
            'title' => 'Aula Publicada',
            'is_published' => true,
            'order_index' => 0,
        ]);
        $draftLesson = Lesson::factory()->for($module)->richText()->create([
            'title' => 'Aula Em Rascunho',
            'is_published' => false,
            'order_index' => 1,
        ]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Aula Publicada');
        $response->assertDontSee('Aula Em Rascunho');
        $response->assertViewHas('modules', function ($modules) use ($publishedLesson, $draftLesson): bool {
            $lessons = $modules->first()->lessons;

            return $lessons->contains($publishedLesson) && ! $lessons->contains($draftLesson);
        });
    }

    public function test_classroom_view_calculates_progress_and_flags_completed_lessons_for_the_authenticated_student(): void
    {
        $alunoA = $this->makeAluno();
        $alunoB = $this->makeAluno();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        $lesson1 = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 0]);
        $lesson2 = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 1]);

        $alunoA->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 50, 'enrolled_at' => now()]);
        $alunoB->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 50, 'enrolled_at' => now()]);

        LessonProgress::query()->create([
            'user_id' => $alunoA->id,
            'lesson_id' => $lesson1->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        LessonProgress::query()->create([
            'user_id' => $alunoB->id,
            'lesson_id' => $lesson2->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($alunoA)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('progressPercentage', 50);
        $response->assertViewHas('completedLessonsCount', 1);
        $response->assertViewHas('modules', function ($modules) use ($lesson1, $lesson2): bool {
            $lessons = $modules->first()->lessons->keyBy('id');

            return $lessons[$lesson1->id]->is_completed === true
                && $lessons[$lesson2->id]->is_completed === false;
        });
    }

    public function test_classroom_view_resolves_next_lesson_in_order_sequence_and_suppresses_when_all_completed(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $module1 = Module::factory()->for($course)->create(['order_index' => 0]);
        $module2 = Module::factory()->for($course)->create(['order_index' => 1]);

        $lesson1 = Lesson::factory()->for($module1)->richText()->create(['title' => 'Primeira Aula', 'is_published' => true, 'order_index' => 0]);
        $lesson2 = Lesson::factory()->for($module2)->richText()->create(['title' => 'Segunda Aula', 'is_published' => true, 'order_index' => 0]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 0, 'enrolled_at' => now()]);

        // 1. Initial: first pending lesson is Lesson 1
        $response1 = $this->actingAs($aluno)->get(route('classroom.show', $course));
        $response1->assertOk();
        $response1->assertSee('Próxima aula');
        $response1->assertSee('Primeira Aula');

        // 2. Complete Lesson 1: next pending is Lesson 2
        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson1->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response2 = $this->actingAs($aluno)->get(route('classroom.show', $course));
        $response2->assertOk();
        $response2->assertSee('Próxima aula');
        $response2->assertSee('Segunda Aula');

        // 3. Complete Lesson 2: all complete -> Next lesson card is suppressed
        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson2->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response3 = $this->actingAs($aluno)->get(route('classroom.show', $course));
        $response3->assertOk();
        $response3->assertDontSee('Próxima aula');
    }

    public function test_classroom_view_passes_issued_certificate_or_null_when_unavailable(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 100, 'enrolled_at' => now()]);

        // 1. Certificate not issued yet
        $responseWithoutCert = $this->actingAs($aluno)->get(route('classroom.show', $course));
        $responseWithoutCert->assertOk();
        $responseWithoutCert->assertViewHas('certificate', null);
        $responseWithoutCert->assertSee('certificate-unavailable', false);
        $responseWithoutCert->assertSee('Certificado ainda não disponível');
        $responseWithoutCert->assertSee('O certificado é emitido quando você cumpre as regras de conclusão.');

        // 2. Certificate issued
        $certificate = Certificate::factory()->for($course)->for($aluno)->create();

        $responseWithCert = $this->actingAs($aluno)->get(route('classroom.show', $course));
        $responseWithCert->assertOk();
        $responseWithCert->assertViewHas('certificate', fn (Certificate $c): bool => $c->id === $certificate->id);
        $responseWithCert->assertSee('Baixar certificado');
    }

    public function test_admin_can_preview_the_classroom_without_enrollment(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $this->actingAsAdmin();

        $response = $this->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee($lesson->title);
    }

    public function test_gestor_from_the_same_org_can_preview_the_classroom_without_enrollment(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee($lesson->title);
    }

    public function test_gestor_from_a_different_org_cannot_view_the_classroom(): void
    {
        $courseOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $courseOrg->id]);
        Module::factory()->for($course)->create();

        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($otherOrg, RolesEnum::GESTOR->value);

        $response = $this->get(route('classroom.show', $course));

        $response->assertForbidden();
    }

    public function test_enrolled_student_can_view_a_lesson_with_its_progress_state(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee($lesson->title);
        $response->assertViewHas('isCompleted', true);
    }

    public function test_enrolled_student_cannot_view_an_unpublished_lesson_directly(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('classroom.lesson', $lesson));

        $response->assertNotFound();
    }

    public function test_admin_and_gestor_can_preview_unpublished_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        // Admin preview
        $this->actingAsAdmin();
        $this->get(route('classroom.lesson', $lesson))->assertOk();

        // Same-org Gestor preview
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $this->get(route('classroom.lesson', $lesson))->assertOk();

        // Other-org Gestor preview
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($otherOrg, RolesEnum::GESTOR->value);
        $this->get(route('classroom.lesson', $lesson))->assertForbidden();
    }

    public function test_classroom_view_exposes_the_normalized_contract_and_drops_the_legacy_aliases(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        $completedLesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 0]);
        $pendingLesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 1]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 50, 'enrolled_at' => now()]);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $completedLesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('completedLessonsCount', 1);
        $response->assertViewHas('totalLessonsCount', 2);
        $response->assertViewHas('progressPercentage', 50);
        $response->assertViewHas('modules', function ($modules) use ($completedLesson): bool {
            $lesson = $modules->first()->lessons->firstWhere('id', $completedLesson->id);

            return $lesson->is_completed === true && $lesson->glyph === 'check';
        });

        $viewData = $response->original->getData();

        $this->assertArrayNotHasKey('completedLessonIds', $viewData);
        $this->assertArrayNotHasKey('completedCount', $viewData);
        $this->assertArrayNotHasKey('totalLessons', $viewData);
    }

    public function test_classroom_view_resolves_lesson_glyphs_from_type_and_attached_media(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        $textLesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 0]);
        $videoLesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true, 'order_index' => 1]);
        $pdfLesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true, 'order_index' => 2]);
        $quizLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true, 'order_index' => 3]);

        // PDF lives ONLY in `lesson_media`, the legacy `lessons.pdf_path` stays null.
        LessonMedia::factory()->pdf()->for($pdfLesson, 'lesson')->create();

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('modules', function ($modules) use ($textLesson, $videoLesson, $pdfLesson, $quizLesson): bool {
            $lessons = $modules->first()->lessons->keyBy('id');

            return $lessons[$textLesson->id]->glyph === 'book-open'
                && $lessons[$videoLesson->id]->glyph === 'play'
                && $lessons[$pdfLesson->id]->glyph === 'file-text'
                && $lessons[$quizLesson->id]->glyph === 'clipboard';
        });
    }

    public function test_classroom_view_receives_a_revoked_certificate_without_offering_the_download(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'progress_percentage' => 100, 'enrolled_at' => now()]);

        $certificate = Certificate::factory()->for($course)->for($aluno)->revoked()->create();

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('certificate', fn (?Certificate $c): bool => $c !== null
            && $c->id === $certificate->id
            && $c->isRevoked());
        $response->assertDontSee('Baixar certificado');
        $response->assertSee('certificate-unavailable', false);
        $response->assertSee('Certificado ainda não disponível');
        $response->assertSee('Este certificado foi revogado pela organização e não pode mais ser baixado.');
        $response->assertDontSee('O certificado fica disponível abaixo.');
    }

    public function test_classroom_view_does_not_run_extra_queries_per_module_or_lesson(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();

        $smallCourse = $this->makeCourseWithTrack($org, modules: 1, lessonsPerModule: 1);
        $largeCourse = $this->makeCourseWithTrack($org, modules: 4, lessonsPerModule: 5);

        $aluno->courses()->attach($smallCourse->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($largeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($aluno);

        // Warm-up request: role/permission and config caches must not skew the first count.
        $this->get(route('classroom.show', $smallCourse))->assertOk();

        $smallQueries = $this->countQueriesFor(route('classroom.show', $smallCourse));
        $largeQueries = $this->countQueriesFor(route('classroom.show', $largeCourse));

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "Classroom query count grew from {$smallQueries} to {$largeQueries} with more modules/lessons (N+1)."
        );
    }

    private function makeCourseWithTrack(Organization $org, int $modules, int $lessonsPerModule): Course
    {
        $course = Course::factory()->create(['org_id' => $org->id]);

        for ($moduleIndex = 0; $moduleIndex < $modules; $moduleIndex++) {
            $module = Module::factory()->for($course)->create(['order_index' => $moduleIndex]);

            for ($lessonIndex = 0; $lessonIndex < $lessonsPerModule; $lessonIndex++) {
                $lesson = Lesson::factory()->for($module)->richText()->create([
                    'is_published' => true,
                    'order_index' => $lessonIndex,
                ]);

                LessonMedia::factory()->pdf()->for($lesson, 'lesson')->create();
            }
        }

        return $course;
    }

    private function countQueriesFor(string $url): int
    {
        $queries = 0;

        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get($url)->assertOk();

        return $queries;
    }
}
