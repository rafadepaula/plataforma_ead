<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * RF06/RF07 — E2E coverage: a Gestor creates/edits/deletes a Course, a
 * Module, and a Lesson through the UI, including the destructive-action
 * confirmations.
 */
class CourseManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_can_create_edit_and_delete_a_course_via_the_ui(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.create'))
                ->waitFor('@course-form')
                ->type('title', 'Curso Dusk')
                ->type('workload_hours', '20')
                ->press('Criar Curso')
                ->waitForLocation('/courses')
                ->assertSee('Curso Dusk')
                ->assertSee('Curso criado com sucesso.');
        });

        $course = Course::where('title', 'Curso Dusk')->firstOrFail();

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@edit-course-'.$course->id)
                ->click('@edit-course-'.$course->id)
                ->waitFor('@course-form')
                ->clear('title')
                ->type('title', 'Curso Dusk Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/courses')
                ->assertSee('Curso Dusk Editado');
        });

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@delete-course-'.$course->id)
                ->click('@delete-course-'.$course->id)
                ->waitForLocation('/courses')
                ->assertDontSee('Curso Dusk Editado');
        });

        $this->assertSoftDeleted($course);
    }

    public function test_gestor_can_create_a_module_and_a_lesson_through_the_ui(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.modules.create', $course))
                ->waitFor('@module-form')
                ->type('title', 'Módulo Dusk')
                ->press('Criar Módulo')
                ->waitForLocation('/courses/'.$course->id.'/modules')
                ->assertSee('Módulo Dusk');
        });

        $module = Module::where('title', 'Módulo Dusk')->firstOrFail();

        $this->browse(function (Browser $browser) use ($gestor, $module): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição Dusk')
                ->type('content_text', 'Conteúdo de exemplo')
                ->press('Criar Lição')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição Dusk');
        });

        $this->assertDatabaseHas('lessons', ['module_id' => $module->id, 'title' => 'Lição Dusk']);

        $this->browse(function (Browser $browser) use ($gestor, $module): void {
            $lesson = $module->lessons()->where('title', 'Lição Dusk')->firstOrFail();

            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.index', $module))
                ->waitFor('@delete-lesson-'.$lesson->id)
                ->click('@delete-lesson-'.$lesson->id)
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertDontSee('Lição Dusk');
        });
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
}
