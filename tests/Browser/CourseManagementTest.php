<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage: a Gestor creates/edits/deletes a Course, a
 * Module, and a Lesson through the UI, including the destructive-action
 * confirmations.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de autoria completa (curso → edição → módulo → lição → remoção da
 * lição → remoção do curso) num único método; o guard de matrículas ativas
 * e a negativa de autorização do Aluno ficam isolados.
 */
class CourseManagementTest extends DuskTestCase
{
    public function test_gestor_course_module_and_lesson_full_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            // 1. Criação do Curso
            $browser->loginAs($gestor)
                ->visit(route('courses.create'))
                ->waitFor('@course-form')
                ->type('title', 'Curso Dusk')
                ->type('workload_hours', '20')
                ->press('Criar Curso')
                ->waitForLocation('/courses')
                ->assertSee('Curso Dusk')
                ->assertSee('Curso criado com sucesso.');

            $course = Course::where('title', 'Curso Dusk')->firstOrFail();

            // 2. Edição do Curso
            $browser->visit(route('courses.index'))
                ->waitFor('@edit-course-'.$course->id)
                ->click('@edit-course-'.$course->id)
                ->waitFor('@course-form')
                ->clear('title')
                ->type('title', 'Curso Dusk Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/courses')
                ->assertSee('Curso Dusk Editado');

            $this->assertDatabaseHas('courses', [
                'id' => $course->id,
                'title' => 'Curso Dusk Editado',
            ]);

            // 3. Módulo dentro do Curso
            $browser->visit(route('courses.modules.create', $course))
                ->waitFor('@module-form')
                ->type('title', 'Módulo Dusk')
                ->press('Criar Módulo')
                ->waitForLocation('/courses/'.$course->id.'/modules')
                ->assertSee('Módulo Dusk');

            $module = Module::where('course_id', $course->id)->where('title', 'Módulo Dusk')->firstOrFail();

            // 4. Lição dentro do Módulo
            $browser->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição Dusk')
                ->type('content_text', 'Conteúdo de exemplo')
                ->press('Criar Lição')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição Dusk');

            $this->assertDatabaseHas('lessons', ['module_id' => $module->id, 'title' => 'Lição Dusk']);

            $lesson = $module->lessons()->where('title', 'Lição Dusk')->firstOrFail();

            // 5. Remoção da Lição
            $browser->visit(route('modules.lessons.index', $module))
                ->waitFor('@delete-lesson-'.$lesson->id)
                ->click('@delete-lesson-'.$lesson->id)
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertDontSee('Lição Dusk');

            // 6. Remoção do Curso (soft delete) e desaparecimento da listagem
            $browser->visit(route('courses.index'))
                ->waitFor('@delete-course-'.$course->id)
                ->click('@delete-course-'.$course->id)
                ->waitForLocation('/courses')
                ->assertDontSee('Curso Dusk Editado');

            $this->assertSoftDeleted($course);
        });
    }

    public function test_a_course_with_active_enrollments_cannot_be_deleted(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($aluno->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@delete-course-'.$course->id)
                ->click('@delete-course-'.$course->id)
                ->waitForLocation('/courses')
                ->assertSee('Não é possível excluir um Curso com matrículas ativas.');
        });

        $this->assertNotSoftDeleted($course);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }

    public function test_aluno_cannot_reach_the_courses_index_via_the_ui(): void
    {
        $aluno = User::factory()->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($aluno): void {
            $browser->loginAs($aluno)
                ->visit(route('courses.index'))
                ->assertSee('403');
        });
    }
}
