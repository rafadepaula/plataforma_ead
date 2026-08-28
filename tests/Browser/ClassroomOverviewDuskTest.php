<?php

namespace Tests\Browser;

use App\Actions\MarkLessonCompleteAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end lifecycle testing of the classroom overview, module/lesson navigation,
 * progress percentage tracking, next lesson shortcuts, and certificate download availability.
 */
class ClassroomOverviewDuskTest extends DuskTestCase
{
    public function test_student_classroom_overview_progression_and_certification_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Acme Treinamentos']);
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Formação de Desenvolvedores',
            'is_published' => true,
        ]);
        CourseCompletionRule::factory()->for($course)->create();

        $module1 = Module::factory()->create([
            'course_id' => $course->id,
            'title' => 'Módulo 1: Fundamentos',
            'order_index' => 0,
        ]);
        $module2 = Module::factory()->create([
            'course_id' => $course->id,
            'title' => 'Módulo 2: Avançado',
            'order_index' => 1,
        ]);

        $lesson1 = Lesson::factory()->richText()->create([
            'module_id' => $module1->id,
            'title' => 'Aula 1.1 Introdução',
            'is_published' => true,
            'order_index' => 0,
        ]);
        $lesson2 = Lesson::factory()->richText()->create([
            'module_id' => $module1->id,
            'title' => 'Aula 1.2 Configuração',
            'is_published' => true,
            'order_index' => 1,
        ]);
        $lesson3 = Lesson::factory()->richText()->create([
            'module_id' => $module2->id,
            'title' => 'Aula 2.1 Banco de Dados',
            'is_published' => true,
            'order_index' => 0,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $module1, $module2, $lesson1, $lesson2, $lesson3): void {
            // 1. Initial visit: verify breadcrumbs, headers, module list, 0% progress and next lesson card
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '0%')
                ->assertVisible('@module-'.$module1->id)
                ->assertVisible('@module-'.$module2->id)
                ->assertVisible('@lesson-'.$lesson1->id)
                ->assertVisible('@lesson-'.$lesson2->id)
                ->assertVisible('@lesson-'.$lesson3->id)
                ->assertVisible('@open-lesson-'.$lesson1->id)
                ->assertVisible('@certificate-unavailable')
                ->assertMissing('@download-certificate')
                ->assertSee('Aula 1.1 Introdução');

            // 2. Click through to the first lesson and mark complete
            $browser->click('@open-lesson-'.$lesson1->id)
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitUntilMissing('@mark-complete-button')
                ->assertVisible('@lesson-completed-badge');

            $this->assertDatabaseHas('lesson_progress', [
                'user_id' => $student->id,
                'lesson_id' => $lesson1->id,
                'is_completed' => true,
                'completion_source' => 'manual_click',
            ]);

            // 3. Return to classroom overview: progress is 33%, lesson 1 shows completed checkmark, next lesson is lesson 2
            $browser->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '33%')
                ->assertVisible('@lesson-completed-'.$lesson1->id)
                ->assertMissing('@lesson-completed-'.$lesson2->id)
                // Contagens parciais: o módulo tocado mostra a fração real, o
                // módulo intocado permanece no ramo "nenhuma".
                ->assertSeeIn('@module-'.$module1->id, '1 de 2 aulas concluídas')
                ->assertSeeIn('@module-'.$module2->id, 'Nenhuma aula concluída')
                ->assertSeeIn('.ds-progress-labels', '1 de 3 aulas concluídas')
                ->assertSee('Aula 1.2 Configuração');

            // 4. Complete remaining lessons to reach 100% course completion
            $action = new MarkLessonCompleteAction;
            $action->execute($lesson2, $student, 'manual_click');
            $action->execute($lesson3, $student, 'manual_click');

            $this->assertDatabaseHas('course_user', [
                'user_id' => $student->id,
                'course_id' => $course->id,
                'progress_percentage' => 100,
                'status' => 'completed',
            ]);

            // 5. Reload classroom overview: 100% progress, all checkmarks visible, next lesson card suppressed, and certificate issued
            $browser->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '100%')
                ->assertVisible('@lesson-completed-'.$lesson1->id)
                ->assertVisible('@lesson-completed-'.$lesson2->id)
                ->assertVisible('@lesson-completed-'.$lesson3->id)
                ->assertVisible('@download-certificate')
                ->assertMissing('@certificate-unavailable');
        });
    }

    /**
     * Modo Visualização: Admin e Gestor da mesma Organização abrem a sala de
     * aula sem matrícula (sem linha em `course_user`, logo progresso zerado),
     * e o Gestor de outra Organização é barrado com 403.
     */
    public function test_staff_preview_the_classroom_without_enrollment_and_foreign_gestor_is_rejected(): void
    {
        $courseOrg = Organization::factory()->create(['name' => 'Organização Dona']);
        $otherOrg = Organization::factory()->create(['name' => 'Organização Estranha']);

        $course = Course::factory()->create([
            'org_id' => $courseOrg->id,
            'title' => 'Curso em Modo Visualização',
            'is_published' => true,
        ]);
        $module = Module::factory()->create([
            'course_id' => $course->id,
            'title' => 'Módulo Visualizado',
            'order_index' => 0,
        ]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Visualizada',
            'is_published' => true,
            'order_index' => 0,
        ]);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $ownGestor = User::factory()->create(['org_id' => $courseOrg->id]);
        $ownGestor->assignRole(RolesEnum::GESTOR->value);

        $foreignGestor = User::factory()->create(['org_id' => $otherOrg->id]);
        $foreignGestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($admin, $ownGestor, $foreignGestor, $course, $module, $lesson): void {
            // 1. Admin sem matrícula: a sala inteira renderiza e a barra lateral
            //    cai para 0% (não há pivot `course_user` para o staff).
            $browser->loginAs($admin)
                ->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertVisible('@module-'.$module->id)
                ->assertVisible('@lesson-'.$lesson->id)
                ->assertVisible('@open-lesson-'.$lesson->id)
                ->assertSee('Aula Visualizada')
                ->assertSeeIn('@course-progress-label', '0%')
                ->assertSeeIn('@module-'.$module->id, 'Nenhuma aula concluída');

            // 2. Gestor da Organização dona do Curso: mesma visualização.
            $browser->loginAs($ownGestor)
                ->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertVisible('@module-'.$module->id)
                ->assertVisible('@lesson-'.$lesson->id)
                ->assertSee('Aula Visualizada')
                ->assertSeeIn('@course-progress-label', '0%');

            // 3. Gestor de outra Organização: 403.
            $browser->loginAs($foreignGestor)
                ->visit(route('classroom.show', $course))
                ->assertSee('403')
                ->assertDontSee('Aula Visualizada');
        });
    }

    public function test_empty_state_when_course_has_no_published_modules(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso Sem Módulos',
            'is_published' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active', 'progress_percentage' => 0]);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@no-modules')
                ->assertVisible('@no-modules')
                ->assertSeeIn('@course-progress-label', '0%');
        });
    }

    /**
     * Builds a published Course with one module, seeds the given Aluno as an
     * active enrollment and returns [$student, $course, $module].
     *
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function makeEnrolledClassroom(int $progressPercentage = 0): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'is_published' => true,
        ]);
        $module = Module::factory()->create([
            'course_id' => $course->id,
            'title' => 'Módulo Único',
            'order_index' => 0,
        ]);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => $progressPercentage,
        ]);

        return [$student, $course, $module];
    }

    public function test_certificate_card_links_the_download_of_the_owning_students_certificate(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom(100);

        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order_index' => 0,
        ]);
        (new MarkLessonCompleteAction)->execute($lesson, $student, 'manual_click');

        $certificate = Certificate::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $certificate): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@download-certificate')
                ->assertMissing('@certificate-unavailable')
                ->assertAttribute('@download-certificate', 'href', route('certificates.download', $certificate));
        });
    }

    public function test_revoked_certificate_shows_the_unavailable_surface_without_a_download_link(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom(100);

        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order_index' => 0,
        ]);
        (new MarkLessonCompleteAction)->execute($lesson, $student, 'manual_click');

        Certificate::factory()->revoked()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@certificate-unavailable')
                ->assertVisible('@certificate-unavailable')
                ->assertSeeIn('@certificate-unavailable', 'Este certificado foi revogado pela organização e não pode mais ser baixado.')
                ->assertMissing('@download-certificate');
        });
    }

    public function test_next_lesson_card_opens_the_first_pending_lesson_and_disappears_once_the_course_is_complete(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom();

        $firstLesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Concluída',
            'is_published' => true,
            'order_index' => 0,
        ]);
        $pendingLesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Pendente',
            'is_published' => true,
            'order_index' => 1,
        ]);

        $action = new MarkLessonCompleteAction;
        $action->execute($firstLesson, $student, 'manual_click');

        $this->browse(function (Browser $browser) use ($student, $course, $pendingLesson, $action): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('.ds-next-lesson')
                ->assertSee('Próxima aula')
                ->assertSeeIn('.ds-next-lesson', 'Aula Pendente')
                ->click('.ds-next-lesson')
                ->waitForLocation(parse_url(route('classroom.lesson', $pendingLesson), PHP_URL_PATH))
                ->assertSee('Aula Pendente');

            $action->execute($pendingLesson, $student, 'manual_click');

            $browser->visit(route('classroom.show', $course))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '100%')
                ->assertMissing('.ds-next-lesson')
                ->assertDontSee('Próxima aula');
        });
    }

    public function test_lesson_rows_render_type_specific_glyphs_and_chips(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom();

        $contentLesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula de Conteúdo',
            'is_published' => true,
            'order_index' => 0,
        ]);
        $quizLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Prova Final',
            'type' => 'quiz',
            'is_published' => true,
            'order_index' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $contentLesson, $quizLesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@lesson-'.$contentLesson->id)
                ->assertVisible('@lesson-'.$contentLesson->id.' .lucide-book-open')
                ->assertSeeIn('@lesson-'.$contentLesson->id.' .ds-chip-outline', 'Conteúdo')
                ->assertVisible('@lesson-'.$quizLesson->id.' .lucide-clipboard')
                ->assertSeeIn('@lesson-'.$quizLesson->id.' .ds-chip-primary', 'Prova');
        });
    }

    public function test_sidebar_stacks_after_the_main_track_on_mobile_viewports(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom();

        Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order_index' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $module): void {
            $browser->resize(375, 900)
                ->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@module-'.$module->id)
                ->assertVisible('@module-'.$module->id)
                ->assertVisible('@course-progress-bar');

            $offsets = $browser->script([
                'return document.querySelector(\'[dusk="module-'.$module->id.'"]\').getBoundingClientRect().top;',
                'return document.querySelector(\'[dusk="course-progress-bar"]\').getBoundingClientRect().top;',
                'return document.documentElement.scrollWidth <= window.innerWidth;',
            ]);

            $this->assertLessThan(
                $offsets[1],
                $offsets[0],
                'On a 375px viewport the sidebar must stack BELOW the module track.'
            );
            $this->assertTrue($offsets[2], 'The classroom overview must not overflow horizontally at 375px.');

            $browser->resize(1440, 900);
        });
    }

    public function test_long_titles_do_not_overflow_on_mobile(): void
    {
        [$student, $course, $module] = $this->makeEnrolledClassroom(100);

        $module->update([
            'title' => 'Módulo de Fundamentos Avançados de Governança Corporativa e Compliance Regulatório Aplicado',
        ]);
        $course->update([
            'title' => 'Formação Continuada em Governança Corporativa, Compliance Regulatório e Gestão de Riscos Institucionais',
        ]);

        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'title' => 'Aula Introdutória sobre Estruturas de Governança, Controles Internos e Auditoria Independente Contínua',
            'is_published' => true,
            'order_index' => 0,
        ]);
        (new MarkLessonCompleteAction)->execute($lesson, $student, 'manual_click');

        Certificate::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $lesson): void {
            $browser->resize(375, 900)
                ->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@lesson-'.$lesson->id)
                ->assertVisible('@download-certificate');

            $fits = $browser->script(
                'return document.documentElement.scrollWidth <= window.innerWidth;'
            );

            $this->assertTrue(
                $fits[0],
                'Long course, module and lesson titles must not overflow horizontally at 375px.'
            );

            $browser->resize(1440, 900);
        });
    }
}
