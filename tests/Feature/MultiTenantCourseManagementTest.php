<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
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

    public function test_courses_index_exposes_module_lesson_and_student_counts_per_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso com Contagens']);
        $moduleOne = Module::factory()->for($course)->create();
        $moduleTwo = Module::factory()->for($course)->create();
        Lesson::factory()->for($moduleOne)->count(2)->create();
        Lesson::factory()->for($moduleTwo)->count(1)->create();
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('courses.index'));

        $response->assertOk();

        $viewCourse = $response->viewData('courses')->firstWhere('id', $course->id);

        $this->assertSame(2, $viewCourse->modules_count);
        $this->assertSame(3, $viewCourse->lessons_count);
        $this->assertSame(1, $viewCourse->students_count);
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
}
