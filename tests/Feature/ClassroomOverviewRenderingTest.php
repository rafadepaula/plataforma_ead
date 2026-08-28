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
 * Rendered-markup contract of the classroom overview screen.
 *
 * MultiOrgStudentClassroomTest guards the data the controller hands the view;
 * this suite guards the HTML that reaches the browser: verbatim header copy,
 * the neutral certificate surface, the strict `lesson-{id}`/`open-lesson-{id}`
 * selector split, and the main-track-before-sidebar DOM order the responsive
 * 8/4 grid depends on.
 */
class ClassroomOverviewRenderingTest extends TestCase
{
    private function makeAluno(): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        return $aluno;
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson}
     */
    private function makeEnrolledClassroom(int $progressPercentage = 0): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order_index' => 0,
        ]);

        $aluno = $this->makeAluno();
        $course->students()->attach($aluno->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => $progressPercentage,
        ]);

        return [$aluno, $course, $module, $lesson];
    }

    public function test_page_header_renders_the_kicker_title_subtitle_and_breadcrumb(): void
    {
        [$aluno, $course] = $this->makeEnrolledClassroom();

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Sala de aula');
        $response->assertSee('Acompanhe os módulos e as lições deste curso e continue de onde parou.');
        $response->assertSee('Meus cursos');
        $response->assertSee(route('student.courses.index'));
        $response->assertSee($course->title);
    }

    public function test_page_header_links_to_the_course_forum(): void
    {
        [$aluno, $course] = $this->makeEnrolledClassroom();

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Fórum do curso');
        $response->assertSee(route('forum.index', $course));
    }

    public function test_pending_certificate_renders_the_neutral_unavailable_surface(): void
    {
        [$aluno, $course] = $this->makeEnrolledClassroom(40);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Certificado ainda não disponível');
        $response->assertSee('dusk="certificate-unavailable"', false);
        $response->assertDontSee('dusk="download-certificate"', false);
    }

    public function test_lesson_selectors_are_split_between_the_list_item_and_the_anchor(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom();

        $content = $this->actingAs($aluno)->get(route('classroom.show', $course))->getContent();

        $this->assertMatchesRegularExpression(
            '/<li[^>]*dusk="lesson-'.$lesson->id.'"/',
            $content,
            'The lesson `<li>` must carry dusk="lesson-{id}".'
        );
        $this->assertMatchesRegularExpression(
            '/<a[^>]*dusk="open-lesson-'.$lesson->id.'"/',
            $content,
            'The lesson navigation `<a>` must carry dusk="open-lesson-{id}".'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<li[^>]*dusk="open-lesson-'.$lesson->id.'"/',
            $content,
            'dusk="open-lesson-{id}" must never land on the `<li>`.'
        );
    }

    public function test_completed_lesson_renders_the_completion_selector(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('dusk="lesson-completed-'.$lesson->id.'"', false);
    }

    public function test_main_track_column_precedes_the_sidebar_column_in_the_dom(): void
    {
        [$aluno, $course] = $this->makeEnrolledClassroom();

        $content = $this->actingAs($aluno)->get(route('classroom.show', $course))->getContent();

        $trackPosition = strpos($content, 'col-lg-8');
        $sidebarPosition = strpos($content, 'col-lg-4');

        $this->assertIsInt($trackPosition, 'The 8-column main track must be rendered.');
        $this->assertIsInt($sidebarPosition, 'The 4-column sidebar must be rendered.');
        $this->assertLessThan(
            $sidebarPosition,
            $trackPosition,
            'The col-lg-8 main track must be declared before the col-lg-4 sidebar so the sidebar stacks last on small screens.'
        );
    }

    public function test_lesson_type_chips_distinguish_quizzes_from_content_lessons(): void
    {
        [$aluno, $course, $module, $contentLesson] = $this->makeEnrolledClassroom();

        $quizLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'type' => 'quiz',
            'is_published' => true,
            'order_index' => 1,
        ]);

        $content = $this->actingAs($aluno)->get(route('classroom.show', $course))->getContent();

        $this->assertMatchesRegularExpression(
            '/dusk="lesson-'.$contentLesson->id.'".*?ds-chip-outline.*?Conteúdo/s',
            $content,
            'A content lesson row must render the outline "Conteúdo" chip.'
        );
        $this->assertMatchesRegularExpression(
            '/dusk="lesson-'.$quizLesson->id.'".*?ds-chip-primary.*?Prova/s',
            $content,
            'A quiz lesson row must render the primary "Prova" chip.'
        );
    }

    public function test_course_without_modules_renders_the_empty_state(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $aluno = $this->makeAluno();
        $course->students()->attach($aluno->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('dusk="no-modules"', false);
        $response->assertSee('dusk="course-progress-label"', false);
    }

    public function test_course_with_modules_but_no_published_lessons_renders_the_empty_state(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Rascunho',
            'is_published' => false,
            'order_index' => 0,
        ]);

        $aluno = $this->makeAluno();
        $course->students()->attach($aluno->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('dusk="no-modules"', false);
        $response->assertSee('Este curso ainda não possui lições publicadas.');
        $response->assertDontSee($module->title);
        $response->assertDontSee('Aula Rascunho');
    }

    public function test_completed_course_without_completion_rules_keeps_the_neutral_certificate_surface(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        $this->assertDatabaseCount('course_completion_rules', 0);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('certificate', null);
        $response->assertSee('dusk="certificate-unavailable"', false);
        $response->assertSee('Certificado ainda não disponível');
        $response->assertDontSee('dusk="download-certificate"', false);
        $response->assertDontSee('Baixar certificado');
        $response->assertDontSee('Este certificado foi revogado pela organização e não pode mais ser baixado.');
        $response->assertDontSee('Curso concluído. O certificado fica disponível abaixo.');
        $response->assertSee('Curso concluído. Acompanhe a situação do certificado abaixo.');
    }

    public function test_completed_course_with_an_issued_certificate_points_the_student_to_the_download(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        Certificate::factory()->create([
            'user_id' => $aluno->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Curso concluído. O certificado fica disponível abaixo.');
        $response->assertSee('Baixar certificado');
        $response->assertDontSee('Curso concluído. Acompanhe a situação do certificado abaixo.');
    }

    public function test_issued_certificate_card_shows_the_code_prefix_and_links_to_the_public_verifier(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        $certificate = Certificate::factory()->create([
            'user_id' => $aluno->id,
            'course_id' => $course->id,
        ]);

        $expectedCode = strtoupper(substr($certificate->validation_hash, 0, 12));

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Certificado nº');
        /** O bloco do código é fechado: guarda o prefixo de 12 e nada além dele. */
        $response->assertSee('<div class="ds-cert-code text-break">'.$expectedCode.'</div>', false);
        $response->assertSee('Verificar autenticidade');
        $response->assertSee(route('certificates.verify', $certificate->validation_hash));
    }

    public function test_unavailable_certificate_card_exposes_neither_code_nor_verification_link(): void
    {
        [$aluno, $course] = $this->makeEnrolledClassroom(40);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertDontSee('Certificado nº');
        $response->assertDontSee('Verificar autenticidade');
    }

    public function test_completed_course_with_a_revoked_certificate_explains_the_revocation(): void
    {
        [$aluno, $course, , $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        Certificate::factory()->revoked()->create([
            'user_id' => $aluno->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee('Certificado ainda não disponível');
        $response->assertSee('Este certificado foi revogado pela organização e não pode mais ser baixado.');
        $response->assertDontSee('Curso concluído. O certificado fica disponível abaixo.');
        $response->assertDontSee('Baixar certificado');
    }

    public function test_staff_preview_without_enrollment_renders_zero_progress(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order_index' => 0,
        ]);

        $this->actingAsAdmin();

        $response = $this->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('progressPercentage', 0);
        $response->assertSee($lesson->title);
        $this->assertMatchesRegularExpression(
            '/dusk="course-progress-label"[^>]*>\s*0%/',
            $response->getContent(),
            'A staff preview has no course_user pivot row, so the progress label must still render 0%.'
        );
    }

    public function test_lesson_unpublished_after_completion_leaves_the_track_while_progress_stays_complete(): void
    {
        [$aluno, $course, $module, $lesson] = $this->makeEnrolledClassroom(100);

        $archivedLesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Arquivada',
            'is_published' => true,
            'order_index' => 1,
        ]);

        foreach ([$lesson, $archivedLesson] as $completedLesson) {
            LessonProgress::query()->create([
                'user_id' => $aluno->id,
                'lesson_id' => $completedLesson->id,
                'is_completed' => true,
                'completed_at' => now(),
                'completion_source' => 'manual_click',
            ]);
        }

        $archivedLesson->update(['is_published' => false]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertDontSee('Aula Arquivada');
        $response->assertViewHas('totalLessonsCount', 1);
        $response->assertViewHas('completedLessonsCount', 1);
        $response->assertSee('dusk="lesson-completed-'.$lesson->id.'"', false);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $archivedLesson->id,
            'is_completed' => true,
        ]);
        $this->assertMatchesRegularExpression(
            '/dusk="course-progress-label"[^>]*>\s*100%/',
            $response->getContent(),
            'Unpublishing a completed lesson must keep 100% reachable at the view level.'
        );
    }

    /**
     * Markup do cabeçalho de um módulo — de `dusk="module-{id}"` até a lista
     * de lições — para que a legenda de conclusão seja asserida NAQUELE
     * módulo, e não em qualquer ponto da página.
     */
    private function moduleHeaderMarkup(string $content, Module $module): string
    {
        $start = strpos($content, 'dusk="module-'.$module->id.'"');
        $this->assertNotFalse($start, 'The module block must be rendered.');

        $listStart = strpos($content, '<ul', (int) $start);
        $end = $listStart === false ? strlen($content) : $listStart;

        return substr($content, (int) $start, $end - (int) $start);
    }

    /**
     * Markup da legenda do card de progresso da barra lateral — de
     * `ds-progress-labels` até `dusk="course-progress-label"`.
     */
    private function progressCardCaptionMarkup(string $content): string
    {
        $end = strpos($content, 'dusk="course-progress-label"');
        $this->assertNotFalse($end, 'The progress card must be rendered.');

        $start = strrpos(substr($content, 0, (int) $end), 'ds-progress-labels');
        $this->assertNotFalse($start, 'The progress card caption row must be rendered.');

        return substr($content, (int) $start, (int) $end - (int) $start);
    }

    public function test_single_lesson_module_uses_the_singular_completion_caption(): void
    {
        [$aluno, $course, $module, $lesson] = $this->makeEnrolledClassroom(100);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        $content = $this->actingAs($aluno)->get(route('classroom.show', $course))->getContent();

        $this->assertStringNotContainsString('1 de 1 aulas concluídas', $content);
        $this->assertStringContainsString(
            '1 de 1 aula concluída',
            $this->moduleHeaderMarkup($content, $module),
            'The module header must use the singular caption.'
        );
        $this->assertStringContainsString(
            '1 de 1 aula concluída',
            $this->progressCardCaptionMarkup($content),
            'The progress card must use the singular caption.'
        );
    }

    public function test_completion_captions_report_the_real_partial_counts(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $startedModule = Module::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $untouchedModule = Module::factory()->create(['course_id' => $course->id, 'order_index' => 1]);

        $lessons = collect();

        foreach (range(0, 2) as $index) {
            $lessons->push(Lesson::factory()->richText()->create([
                'module_id' => $startedModule->id,
                'is_published' => true,
                'order_index' => $index,
            ]));
        }

        foreach (range(0, 1) as $index) {
            Lesson::factory()->richText()->create([
                'module_id' => $untouchedModule->id,
                'is_published' => true,
                'order_index' => $index,
            ]);
        }

        $aluno = $this->makeAluno();
        $course->students()->attach($aluno->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 20,
        ]);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lessons->first()->id,
            'is_completed' => true,
            'completed_at' => now(),
            'completion_source' => 'manual_click',
        ]);

        $response = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertViewHas('completedLessonsCount', 1);
        $response->assertViewHas('totalLessonsCount', 5);

        $content = $response->getContent();

        $this->assertStringContainsString(
            '1 de 3 aulas concluídas',
            $this->moduleHeaderMarkup($content, $startedModule),
            'The started module must report its own partial count, never its lesson total.'
        );
        $this->assertStringContainsString(
            'Nenhuma aula concluída',
            $this->moduleHeaderMarkup($content, $untouchedModule),
            'A module without completed lessons must keep the neutral caption.'
        );
        $this->assertStringContainsString(
            '1 de 5 aulas concluídas',
            $this->progressCardCaptionMarkup($content),
            'The sidebar must report the course-wide partial count, never the lesson total.'
        );
    }
}
