<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the student learning experience: "Meus Cursos" (SPEC-26's
 * `x-course.card` grid), the "Começar curso" CTA landing directly on the
 * first published lesson, manually completing a text lesson, seeing the
 * course progress bar reflect it, and the idempotency of a second
 * completion call.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de aprendizagem inteira num método; as negativas de acesso (não
 * matriculado e matrícula cancelada) exigem outros atores e ficam isoladas.
 */
class MultiOrgStudentClassroomTest extends DuskTestCase
{
    public function test_student_classroom_and_lesson_completion_lifecycle(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);
        $orgB = Organization::factory()->create(['name' => 'Organização B']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);

        $module = Module::factory()->create(['course_id' => $courseA->id]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $courseB->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $courseA, $lesson): void {
            // 1. "Meus Cursos" (grade de cards, sem mais seções por
            //    Organização — o nome dela vive na overline de cada card) e o
            //    CTA "Começar curso" (curso `nao_iniciado`) leva direto à
            //    primeira aula publicada.
            $browser->loginAs($student)
                ->visit('/meus-cursos')
                ->waitFor('@course-card-'.$courseA->id)
                // `.kicker` (the card's org overline) is `text-transform:
                // uppercase`, so the rendered text is not a literal-case
                // match — see the laravel-dusk skill's `assertSeeIn` note.
                ->assertSeeIgnoringCase('Organização A')
                ->assertSeeIgnoringCase('Organização B')
                ->click('@course-continue-'.$courseA->id)
                ->waitFor('@mark-complete-button');

            // 2. Conclusão manual da lição de texto.
            $browser->assertVisible('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitUntilMissing('@mark-complete-button')
                ->assertVisible('@lesson-completed-badge');

            $this->assertDatabaseHas('lesson_progress', [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'completion_source' => 'manual_click',
            ]);

            // 3. O progresso do Curso reflete a conclusão.
            $browser->visit(route('classroom.show', $courseA))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '100%')
                ->assertVisible('@lesson-completed-'.$lesson->id);

            $this->assertDatabaseHas('course_user', [
                'user_id' => $student->id,
                'course_id' => $courseA->id,
                'progress_percentage' => 100,
            ]);

            $progressAfterFirstClick = DB::table('course_user')
                ->where('user_id', $student->id)
                ->where('course_id', $courseA->id)
                ->value('progress_percentage');

            // 4. Idempotência: uma segunda chamada de conclusão não duplica
            //    progresso.
            //
            //    Não há caminho de UI para reclicar: com `is_completed` true,
            //    `ClassroomController@showLesson` rerenderiza o botão com
            //    `hidden` e `LessonPlayer.reflectCompletion()` nunca o traz de
            //    volta. Então, após recarregar a página (provando que o estado
            //    concluído sobrevive a um render novo), dispara-se exatamente o
            //    mesmo POST `lessons.complete` que o botão emitiria — via XHR
            //    síncrono carregando o CSRF token da própria página.
            $browser->visit(route('classroom.lesson', $lesson))
                ->waitFor('@lesson-completed-badge')
                ->assertVisible('@lesson-completed-badge')
                ->assertMissing('@mark-complete-button')
                ->script(sprintf(
                    "var xhr = new XMLHttpRequest();
                    xhr.open('POST', %s, false);
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'));
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.send();",
                    json_encode(route('lessons.complete', $lesson))
                ));

            $browser->visit(route('classroom.show', $courseA))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '100%');

            $progressAfterSecondCall = DB::table('course_user')
                ->where('user_id', $student->id)
                ->where('course_id', $courseA->id)
                ->value('progress_percentage');

            $this->assertDatabaseCount('lesson_progress', 1);
            $this->assertSame($progressAfterFirstClick, $progressAfterSecondCall);
        });
    }

    public function test_a_student_who_is_not_enrolled_cannot_access_the_classroom(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);

        $notEnrolledStudent = User::factory()->create(['org_id' => $orgA->id]);
        $notEnrolledStudent->assignRole(RolesEnum::ALUNO->value);

        $cancelledStudent = User::factory()->create(['org_id' => $orgA->id]);
        $cancelledStudent->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($cancelledStudent->id, ['enrolled_at' => now(), 'status' => 'cancelled']);

        $this->browse(function (Browser $browser) use ($notEnrolledStudent, $cancelledStudent, $courseA): void {
            // 1. Nunca matriculado: 403.
            $browser->loginAs($notEnrolledStudent)
                ->visit(route('classroom.show', $courseA))
                ->assertSee('403');

            // 2. Matrícula cancelada: também 403.
            $browser->loginAs($cancelledStudent)
                ->visit(route('classroom.show', $courseA))
                ->assertSee('403');
        });

        $this->assertDatabaseCount('lesson_progress', 0);
    }
}
