<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CourseManagementTest extends DuskTestCase
{
    public function test_gestor_course_module_and_lesson_full_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $publishedCourse = Course::factory()->published()->create([
            'org_id' => $org->id,
            'title' => 'Academia Publicada',
        ]);
        $draftCourse = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Biblioteca Rascunho',
        ]);

        foreach (range(1, 16) as $number) {
            Course::factory()->create([
                'org_id' => $org->id,
                'title' => sprintf('Curso Paginado %02d', $number),
                'is_published' => $number % 2 === 0,
            ]);
        }

        $this->browse(function (Browser $browser) use ($draftCourse, $gestor, $publishedCourse): void {
            // 1. Entrada no fluxo de criação pelo catálogo
            $browser->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@new-course')
                ->click('@new-course')
                ->waitForLocation('/courses/create')
                ->waitFor('@course-form')
                ->type('title', 'Curso Dusk')
                ->type('workload_hours', '20')
                ->press('Criar Curso')
                ->waitForLocation('/courses')
                ->assertSee('Curso Dusk')
                ->assertSee('Curso criado com sucesso.');

            $course = Course::where('title', 'Curso Dusk')->firstOrFail();

            $this->assertDatabaseHas('courses', [
                'id' => $course->id,
                'title' => 'Curso Dusk',
                'deleted_at' => null,
            ]);

            // 2. Estrutura, dados e contratos de ação do catálogo
            $browser->assertSeeIn('h1', 'Cursos')
                ->assertPresent('@new-course')
                ->assertPresent('#courses-filter-form[role="search"]')
                ->assertPresent('#search[name="search"]')
                ->assertPresent('button[name="status"][value="all"]')
                ->assertPresent('button[name="status"][value="published"]')
                ->assertPresent('button[name="status"][value="draft"]')
                ->assertPresent('#courses-filter-reset')
                ->assertSeeIn('#courses-table', 'TÍTULO')
                ->assertSeeIn('#courses-table', 'CARGA HORÁRIA')
                ->assertSeeIn('#courses-table', 'ALUNOS')
                ->assertSeeIn('#courses-table', 'STATUS')
                ->assertSeeIn('#courses-table', 'AÇÕES')
                ->assertSeeIn('@course-row-'.$course->id, 'Sem módulos cadastrados')
                ->assertSeeIn('@course-row-'.$course->id, '20 horas')
                ->assertSeeIn('@course-row-'.$course->id, 'Rascunho')
                ->assertAttribute('@manage-modules-'.$course->id, 'href', route('courses.modules.index', $course))
                ->assertAttribute('@manage-completion-rules-'.$course->id, 'href', route('courses.completion-rules.index', $course))
                ->assertAttribute('@edit-course-'.$course->id, 'href', route('courses.edit', $course))
                ->assertAttribute('@delete-course-'.$course->id, 'data-bs-target', '#delete-course-'.$course->id);

            foreach (['course-row', 'manage-modules', 'manage-completion-rules', 'edit-course', 'delete-course'] as $action) {
                $selectorCount = $browser->script(
                    "return document.querySelectorAll('[dusk=\"{$action}-{$course->id}\"]').length;",
                )[0];

                $this->assertSame(1, $selectorCount);
            }

            // 3. Busca textual e estado vazio filtrado
            $browser->type('#search', 'Academia')
                ->clickAndWaitForReload('button[name="status"][value="all"]')
                ->assertQueryStringHas('search', 'Academia')
                ->assertSee($publishedCourse->title)
                ->assertDontSee($draftCourse->title)
                ->clear('#search')
                ->type('#search', 'Resultado inexistente')
                ->clickAndWaitForReload('button[name="status"][value="all"]')
                ->assertSeeIn('#courses-table', 'Nenhum curso cadastrado')
                ->assertSeeIn('#courses-table', 'Crie o primeiro curso para começar a matricular alunos.')
                ->assertAttribute('#courses-table a[href="'.route('courses.create').'"]', 'href', route('courses.create'));

            // 4. Abas de status e limpeza de filtros
            $browser->clickAndWaitForReload('#courses-filter-reset')
                ->clickAndWaitForReload('button[name="status"][value="published"]')
                ->assertQueryStringHas('status', 'published')
                ->assertSee($publishedCourse->title)
                ->assertDontSee($draftCourse->title)
                ->clickAndWaitForReload('button[name="status"][value="draft"]')
                ->assertQueryStringHas('status', 'draft')
                ->assertSee($draftCourse->title)
                ->assertDontSee($publishedCourse->title)
                ->clickAndWaitForReload('#courses-filter-reset')
                ->assertInputValue('#search', '')
                ->assertSee($publishedCourse->title)
                ->assertSee($draftCourse->title);

            // 5. Paginação do catálogo
            $browser->assertSee('Mostrando 1–10 de 19 cursos')
                ->assertPresent('.ds-pagination')
                ->assertVisible('.ds-pagination a[data-page="2"]')
                ->clickAndWaitForReload('.ds-pagination a[data-page="2"]')
                ->assertQueryStringHas('page', '2')
                ->assertSee('Curso Paginado 16');

            // 6. Edição do curso
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

            // 7. Módulo dentro do curso
            $browser->visit(route('courses.modules.create', $course))
                ->waitFor('@module-form')
                ->type('title', 'Módulo Dusk')
                ->press('Criar Módulo')
                ->waitForLocation('/courses/'.$course->id.'/modules')
                ->assertSee('Módulo Dusk');

            $module = Module::where('course_id', $course->id)->where('title', 'Módulo Dusk')->firstOrFail();

            $this->assertDatabaseHas('modules', [
                'id' => $module->id,
                'course_id' => $course->id,
                'title' => 'Módulo Dusk',
            ]);

            // 8. Lição dentro do módulo
            $browser->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição Dusk')
                ->type('content_text', 'Conteúdo de exemplo')
                ->press('Criar Lição')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição Dusk');

            $this->assertDatabaseHas('lessons', ['module_id' => $module->id, 'title' => 'Lição Dusk']);

            $lesson = $module->lessons()->where('title', 'Lição Dusk')->firstOrFail();

            // 9. Contadores do catálogo após a criação de conteúdo
            $browser->visit(route('courses.index'))
                ->waitFor('@course-row-'.$course->id)
                ->assertSeeIn('@course-row-'.$course->id, '1 módulo · 1 aula');

            $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);

            // 10. Remoção da lição
            $browser->visit(route('modules.lessons.index', $module))
                ->waitFor('@open-delete-lesson-'.$lesson->id)
                ->click('@open-delete-lesson-'.$lesson->id)
                ->waitFor('#delete-lesson-modal-'.$lesson->id.'.show')
                ->clickAndWaitForReload('@delete-lesson-'.$lesson->id)
                ->assertDontSee('Lição Dusk');

            $this->assertSoftDeleted($lesson);

            // 11. Confirmação e remoção lógica do curso
            $browser->visit(route('courses.index'))
                ->waitFor('@delete-course-'.$course->id)
                ->click('@delete-course-'.$course->id)
                ->waitFor('#delete-course-'.$course->id.'.show')
                ->assertPresent('form[dusk="delete-form-'.$course->id.'"]')
                ->assertVisible('@delete-form-'.$course->id)
                ->assertSeeIn('#delete-course-'.$course->id, 'Remover curso')
                ->assertSeeIn('#delete-course-'.$course->id, 'Remover “Curso Dusk Editado” é uma ação permanente.')
                ->assertSeeIn('#delete-course-'.$course->id, 'Cursos com matrículas ativas não podem ser removidos.')
                ->clickAndWaitForReload('@confirm-modal-delete-course-'.$course->id.'-confirm')
                ->assertSee('Curso removido com sucesso.')
                ->assertDontSee('Curso Dusk Editado');

            $this->assertSoftDeleted($course);
        });
    }

    public function test_course_with_active_enrollment_delete_is_blocked(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($aluno->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($aluno, $gestor, $course): void {
            // 1. Catálogo apresenta o motivo e desabilita a ação destrutiva
            $browser->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@delete-course-'.$course->id)
                ->assertDisabled('@delete-course-'.$course->id)
                ->assertSeeIn('@course-row-'.$course->id, '1 aluno matriculado')
                ->assertAttributeMissing('@delete-course-'.$course->id, 'data-bs-target')
                ->assertMissing('#delete-course-'.$course->id)
                ->assertMissing('@confirm-modal-delete-course-'.$course->id.'-confirm');

            // 2. Curso e matrícula permanecem intactos
            $this->assertDatabaseHas('course_user', [
                'course_id' => $course->id,
                'user_id' => $aluno->id,
                'status' => 'active',
            ]);
        });

        $this->assertNotSoftDeleted($course);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }
}
