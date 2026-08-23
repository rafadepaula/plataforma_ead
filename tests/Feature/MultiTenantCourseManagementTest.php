<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\TestCase;

/**
 *  Course/Module CRUD is reserved to `role:admin|gestor`, scoped to
 * the acting Gestor's own Organization via `OrgScope`. Also covers the
 * delete guard ( acceptance criteria): a Course with an `active`
 * enrollment may never be soft-deleted.
 */
class MultiTenantCourseManagementTest extends TestCase
{
    public function test_gestor_can_view_the_courses_index_scoped_to_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso da Minha Org']);
        Course::factory()->create(['org_id' => $otherOrg->id, 'title' => 'Curso de Outra Org']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertViewIs('courses.index')
            ->assertSee('Curso da Minha Org')
            ->assertDontSee('Curso de Outra Org');
    }

    public function test_courses_catalog_renders_explicit_column_classes_and_alignment(): void
    {
        $org = Organization::factory()->create();
        Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('courses.index'));

        $response->assertOk();

        $xpath = $this->xpathForHtml($response->getContent());
        $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " course-catalog-table ")]');

        $this->assertNotFalse($tables);
        $this->assertCount(1, $tables);

        $table = $tables->item(0);
        $this->assertInstanceOf(DOMElement::class, $table);
        $this->assertSame(1, $xpath->query('./thead/tr/th[@scope="col" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-workload-column ") and normalize-space(.)="Carga horária"]', $table)?->count());
        $this->assertSame(1, $xpath->query('./thead/tr/th[@scope="col" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-students-column ") and normalize-space(.)="Alunos"]', $table)?->count());
        $this->assertSame(1, $xpath->query('./thead/tr/th[@scope="col" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-status-column ") and normalize-space(.)="Status"]', $table)?->count());
        $this->assertSame(1, $xpath->query('./thead/tr/th[@scope="col" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-actions-column ") and normalize-space(.)="Ações"]', $table)?->count());
        $this->assertSame(1, $xpath->query('./tbody/tr/td[@data-label="Alunos" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-students-column ") and contains(concat(" ", normalize-space(@class), " "), " ds-tabular-nums ")]', $table)?->count());
        $this->assertSame(1, $xpath->query('./tbody/tr/td[@data-label="Ações" and contains(concat(" ", normalize-space(@class), " "), " course-catalog-actions-column ")]', $table)?->count());
    }

    public function test_courses_index_filters_by_partial_title_without_leaking_other_organizations(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        Course::factory()->create(['org_id' => $org->id, 'title' => 'Formação em Liderança']);
        Course::factory()->create(['org_id' => $org->id, 'title' => 'Introdução ao Atendimento']);
        Course::factory()->create(['org_id' => $otherOrg->id, 'title' => 'Liderança de Equipes']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('courses.index', ['search' => 'Liderança']));

        $response->assertOk();

        $courses = $response->viewData('courses');

        $this->assertSame(['Formação em Liderança'], $courses->pluck('title')->all());
    }

    public function test_courses_index_combines_title_and_publication_status_filters(): void
    {
        $org = Organization::factory()->create();
        Course::factory()->published()->create(['org_id' => $org->id, 'title' => 'Gestão Publicada']);
        Course::factory()->create(['org_id' => $org->id, 'title' => 'Gestão em Rascunho']);
        Course::factory()->published()->create(['org_id' => $org->id, 'title' => 'Comunicação Publicada']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $publishedResponse = $this->get(route('courses.index', [
            'search' => 'Gestão',
            'status' => 'published',
        ]));
        $draftResponse = $this->get(route('courses.index', [
            'search' => 'Gestão',
            'status' => 'draft',
        ]));

        $this->assertSame(
            ['Gestão Publicada'],
            $publishedResponse->viewData('courses')->pluck('title')->all(),
        );
        $this->assertSame(
            ['Gestão em Rascunho'],
            $draftResponse->viewData('courses')->pluck('title')->all(),
        );
    }

    public function test_courses_index_ignores_array_shaped_and_unknown_filters(): void
    {
        $org = Organization::factory()->create();
        Course::factory()->published()->create(['org_id' => $org->id, 'title' => 'Curso Publicado']);
        Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso em Rascunho']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $arrayResponse = $this->get(route('courses.index', [
            'search' => ['texto indevido'],
            'status' => ['published'],
        ]));
        $unknownStatusResponse = $this->get(route('courses.index', ['status' => 'archived']));

        $arrayResponse->assertOk();
        $unknownStatusResponse->assertOk();
        $this->assertSame(2, $arrayResponse->viewData('courses')->total());
        $this->assertSame(2, $unknownStatusResponse->viewData('courses')->total());
    }

    public function test_courses_index_paginates_ten_courses_and_preserves_filters_in_page_links(): void
    {
        $org = Organization::factory()->create();
        Course::factory()->published()->count(11)->create([
            'org_id' => $org->id,
            'title' => 'Formação Continuada',
        ]);
        Course::factory()->count(2)->create([
            'org_id' => $org->id,
            'title' => 'Formação em Rascunho',
        ]);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('courses.index', [
            'search' => 'Formação',
            'status' => 'published',
        ]));

        $response->assertOk();

        $courses = $response->viewData('courses');

        $this->assertSame(10, $courses->perPage());
        $this->assertSame(11, $courses->total());
        $this->assertCount(10, $courses->items());
        $this->assertStringContainsString('search=Forma%C3%A7%C3%A3o', $courses->nextPageUrl());
        $this->assertStringContainsString('status=published', $courses->nextPageUrl());
    }

    public function test_courses_index_exposes_module_lesson_and_student_counts_per_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso com Contagens']);
        $moduleOne = Module::factory()->for($course)->create();
        $moduleTwo = Module::factory()->for($course)->create();
        Lesson::factory()->for($moduleOne)->count(2)->create();
        Lesson::factory()->for($moduleTwo)->count(1)->create();
        $activeStudent = User::factory()->create(['org_id' => $org->id]);
        $cancelledStudent = User::factory()->create(['org_id' => $org->id]);
        $completedStudent = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($activeStudent->id, ['enrolled_at' => now(), 'status' => 'active']);
        $course->students()->attach($cancelledStudent->id, ['enrolled_at' => now(), 'status' => 'cancelled']);
        $course->students()->attach($completedStudent->id, ['enrolled_at' => now(), 'status' => 'completed']);

        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('courses.index'));

        $response->assertOk();

        $viewCourse = $response->viewData('courses')->firstWhere('id', $course->id);

        $this->assertSame(2, $viewCourse->modules_count);
        $this->assertSame(3, $viewCourse->lessons_count);
        $this->assertSame(3, $viewCourse->students_count);
        $this->assertSame(1, $viewCourse->active_students_count);
    }

    public function test_aluno_is_forbidden_from_the_courses_index(): void
    {
        $this->actingAsOrgUser(role: RolesEnum::ALUNO->value);

        $this->get(route('courses.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_away_from_the_courses_index(): void
    {
        $this->get(route('courses.index'))->assertRedirect();
    }

    public function test_gestor_can_create_a_course_scoped_to_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->post(route('courses.store'), [
            'title' => 'Curso Novo',
            'description' => 'Descrição do curso',
            'workload_hours' => 40,
            'is_published' => true,
        ]);

        $response->assertRedirect(route('courses.index'));
        $this->assertDatabaseHas('courses', [
            'title' => 'Curso Novo',
            'org_id' => $org->id,
            'workload_hours' => 40,
        ]);
    }

    public function test_course_store_validation_failure_does_not_create_a_record(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $courseCount = Course::withoutGlobalScopes()->count();

        $this->from(route('courses.create'))
            ->post(route('courses.store'), [
                'title' => '',
                'workload_hours' => -1,
            ])
            ->assertRedirect(route('courses.create'))
            ->assertSessionHasErrors(['title', 'workload_hours']);

        $this->assertSame($courseCount, Course::withoutGlobalScopes()->count());
    }

    public function test_course_store_ignores_spoofed_organization_id(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->post(route('courses.store'), [
            'title' => 'Curso Protegido',
            'workload_hours' => 12,
            'org_id' => $otherOrg->id,
        ])->assertRedirect(route('courses.index'));

        $this->assertDatabaseHas('courses', [
            'title' => 'Curso Protegido',
            'org_id' => $org->id,
        ]);
        $this->assertDatabaseMissing('courses', [
            'title' => 'Curso Protegido',
            'org_id' => $otherOrg->id,
        ]);
    }

    public function test_aluno_cannot_create_a_course(): void
    {
        $this->actingAsOrgUser(role: RolesEnum::ALUNO->value);

        $this->post(route('courses.store'), [
            'title' => 'Curso Proibido',
            'workload_hours' => 10,
        ])->assertForbidden();
    }

    public function test_gestor_cannot_view_the_edit_form_for_another_orgs_course(): void
    {
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // OrgScope hides the row entirely for a Gestor of a different org,
        // so route-model binding itself 404s before authorization runs.
        $this->get(route('courses.edit', $course))->assertNotFound();
    }

    public function test_gestor_can_update_their_own_orgs_course(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Nome Antigo']);

        $response = $this->put(route('courses.update', $course), [
            'title' => 'Nome Novo',
            'workload_hours' => 20,
        ]);

        $response->assertRedirect(route('courses.index'));
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Nome Novo']);
    }

    public function test_same_organization_aluno_cannot_update_a_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso Preservado',
            'workload_hours' => 16,
        ]);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->put(route('courses.update', $course), [
            'title' => 'Alteração Indevida',
            'workload_hours' => 32,
        ])->assertForbidden();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'org_id' => $org->id,
            'title' => 'Curso Preservado',
            'workload_hours' => 16,
        ]);
    }

    public function test_course_update_validation_failure_preserves_the_record(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Nome Preservado',
            'workload_hours' => 18,
        ]);

        $this->from(route('courses.edit', $course))
            ->put(route('courses.update', $course), [
                'title' => '',
                'workload_hours' => 70000,
            ])
            ->assertRedirect(route('courses.edit', $course))
            ->assertSessionHasErrors(['title', 'workload_hours']);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'org_id' => $org->id,
            'title' => 'Nome Preservado',
            'workload_hours' => 18,
        ]);
    }

    public function test_course_update_ignores_spoofed_organization_id(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->put(route('courses.update', $course), [
            'title' => 'Curso Atualizado',
            'workload_hours' => 24,
            'org_id' => $otherOrg->id,
        ])->assertRedirect(route('courses.index'));

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'org_id' => $org->id,
            'title' => 'Curso Atualizado',
        ]);
        $this->assertDatabaseMissing('courses', [
            'id' => $course->id,
            'org_id' => $otherOrg->id,
        ]);
    }

    public function test_gestor_can_soft_delete_a_course_with_no_active_enrollments(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->delete(route('courses.destroy', $course))
            ->assertRedirect(route('courses.index'));

        $this->assertSoftDeleted($course);
    }

    public function test_deleting_a_course_with_an_active_enrollment_redirects_back_with_a_flashed_error(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->from(route('courses.index'))
            ->delete(route('courses.destroy', $course))
            ->assertRedirect(route('courses.index'))
            ->assertSessionHas('error', 'Não é possível excluir um Curso com matrículas ativas.');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_course_with_an_active_enrollment_via_json_returns_422(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->deleteJson(route('courses.destroy', $course))
            ->assertStatus(422)
            ->assertJson(['message' => 'Não é possível excluir um Curso com matrículas ativas.']);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_course_with_only_cancelled_or_completed_enrollments_succeeds(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $cancelled = User::factory()->create(['org_id' => $org->id]);
        $completed = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($cancelled->id, ['enrolled_at' => now(), 'status' => 'cancelled']);
        $course->students()->attach($completed->id, ['enrolled_at' => now(), 'status' => 'completed']);

        $this->delete(route('courses.destroy', $course))
            ->assertRedirect(route('courses.index'));

        $this->assertSoftDeleted($course);
    }

    public function test_aluno_cannot_delete_a_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->delete(route('courses.destroy', $course))->assertForbidden();
    }

    public function test_gestor_can_manage_modules_scoped_to_their_own_courses(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->get(route('courses.modules.index', $course))
            ->assertOk()
            ->assertViewIs('courses.modules.index');

        $response = $this->post(route('courses.modules.store', $course), [
            'title' => 'Módulo 1',
            'description' => 'Descrição',
        ]);

        $response->assertRedirect(route('courses.modules.index', $course));
        $this->assertDatabaseHas('modules', ['course_id' => $course->id, 'title' => 'Módulo 1']);

        $module = $course->modules()->first();

        $this->put(route('modules.update', $module), [
            'title' => 'Módulo 1 Atualizado',
            'description' => 'Descrição Atualizada',
        ])->assertRedirect(route('courses.modules.index', $course));
        $this->assertDatabaseHas('modules', ['id' => $module->id, 'title' => 'Módulo 1 Atualizado']);

        $this->delete(route('modules.destroy', $module))
            ->assertRedirect(route('courses.modules.index', $course));
        $this->assertSoftDeleted($module);
    }

    public function test_gestor_is_forbidden_from_managing_modules_of_another_orgs_course_by_guessing_the_id(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $otherModule = $otherCourse->modules()->create(['title' => 'Módulo Alheio', 'order_index' => 0]);

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->get(route('modules.edit', $otherModule))->assertForbidden();
        $this->put(route('modules.update', $otherModule), [
            'title' => 'Invasão',
            'description' => 'Invasão',
        ])->assertForbidden();
        $this->delete(route('modules.destroy', $otherModule))->assertForbidden();
        $this->assertDatabaseHas('modules', ['id' => $otherModule->id, 'deleted_at' => null]);
    }

    private function xpathForHtml(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        return new DOMXPath($document);
    }
}
