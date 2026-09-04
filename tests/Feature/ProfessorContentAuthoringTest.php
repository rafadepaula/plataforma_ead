<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 *  the Professor role's content-authoring surface: an assigned
 * Professor (`User::teaches()`) owns the Course's modules and lessons
 * (`ModulePolicy`/`LessonPolicy::authorizeForCourse()`), including both
 * AJAX reorder endpoints — while the Course's own CRUD (`courses.edit`,
 * `courses.store`) stays `role:admin|gestor` and therefore 403 to him.
 * Payload shapes mirror `MultiTenantCourseManagementTest`; the reorder
 * body is the flat `ordered_ids` array of `ReorderModulesRequest`/
 * `ReorderLessonsRequest`.
 */
class ProfessorContentAuthoringTest extends TestCase
{
    private function assignedProfessor(Organization $org): array
    {
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);

        // Same attach call shape the assignment panel uses.
        $course->professors()->attach($professor->id, ['assigned_by' => $professor->id]);

        $this->actingAs($professor);

        return [$professor, $course];
    }

    public function test_assigned_professor_creates_updates_deletes_and_reorders_modules(): void
    {
        $org = Organization::factory()->create();
        [, $course] = $this->assignedProfessor($org);

        $this->post(route('courses.modules.store', $course), [
            'title' => 'Módulo do Professor',
            'description' => 'Autoria docente',
        ])->assertRedirect(route('courses.modules.index', $course));

        $this->assertDatabaseHas('modules', ['course_id' => $course->id, 'title' => 'Módulo do Professor']);

        $module = $course->modules()->where('title', 'Módulo do Professor')->first();

        $this->put(route('modules.update', $module), [
            'title' => 'Módulo do Professor Atualizado',
            'description' => 'Autoria docente revisada',
        ])->assertRedirect(route('courses.modules.index', $course));

        $this->assertDatabaseHas('modules', ['id' => $module->id, 'title' => 'Módulo do Professor Atualizado']);

        $this->delete(route('modules.destroy', $module))
            ->assertRedirect(route('courses.modules.index', $course));

        $this->assertSoftDeleted($module);
    }

    /**
     * Reorder authz mirrors the module CRUD itself:
     * `ReorderModulesRequest::authorize()` resolves
     * `ModulePolicy::authorizeForCourse()` (via `create`, same as
     * `StoreModuleRequest`), so the assigned Professor reorders his
     * Course's modules exactly like he creates/edits/deletes them — while
     * a NON-assigned professor stays out. (`order_index` is the 0-based
     * array position of `ordered_ids`, per `ModuleController::reorder()`.)
     */
    public function test_assigned_professor_reorders_modules_of_their_course(): void
    {
        $org = Organization::factory()->create();
        [, $course] = $this->assignedProfessor($org);
        $first = Module::factory()->for($course)->create(['order_index' => 0]);
        $second = Module::factory()->for($course)->create(['order_index' => 1]);

        $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }

    /**
     * A Professor of ANOTHER course (same Organization) cannot reorder —
     * `User::teaches()` is the whole boundary, mirroring every other
     * module-write ability above.
     */
    public function test_unassigned_professor_cannot_reorder_modules_of_a_foreign_course(): void
    {
        $org = Organization::factory()->create();
        [$professor] = $this->assignedProfessor($org);
        $otherCourse = Course::factory()->create(['org_id' => $org->id]);
        $first = Module::factory()->for($otherCourse)->create(['order_index' => 0]);
        $second = Module::factory()->for($otherCourse)->create(['order_index' => 1]);

        $this->postJson(route('modules.reorder', $otherCourse), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertForbidden();

        $this->assertSame(0, $first->fresh()->order_index);
        $this->assertSame(1, $second->fresh()->order_index);
    }

    public function test_assigned_professor_creates_updates_and_deletes_lessons(): void
    {
        $org = Organization::factory()->create();
        [, $course] = $this->assignedProfessor($org);
        $module = Module::factory()->for($course)->create();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Aula do Professor',
            'type' => 'content',
            'content_text' => '<p>Conteúdo da aula</p>',
            'is_published' => true,
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Aula do Professor',
            'is_published' => true,
        ]);

        $lesson = $module->lessons()->where('title', 'Aula do Professor')->first();

        $this->put(route('lessons.update', $lesson), [
            'title' => 'Aula do Professor Atualizada',
            'type' => 'content',
            'content_text' => '<p>Conteúdo revisado</p>',
            'is_published' => false,
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => 'Aula do Professor Atualizada',
            'is_published' => false,
        ]);

        $this->delete(route('lessons.destroy', $lesson))
            ->assertRedirect(route('modules.lessons.index', $module));

        $this->assertSoftDeleted($lesson);
    }

    public function test_assigned_professor_reorders_lessons_of_their_course(): void
    {
        $org = Organization::factory()->create();
        [, $course] = $this->assignedProfessor($org);
        $module = Module::factory()->for($course)->create();
        $first = Lesson::factory()->for($module)->create(['order_index' => 0]);
        $second = Lesson::factory()->for($module)->create(['order_index' => 1]);

        $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }

    public function test_unassigned_professor_from_the_same_org_is_forbidden_on_every_content_endpoint(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $this->actingAs($professor);

        // `Module`/`Lesson` carry no `OrgScope`, so the bindings resolve and
        // the policies themselves are what must return 403 — deterministic,
        // never a 404 from tenant filtering.
        $this->post(route('courses.modules.store', $course), [
            'title' => 'Invasão',
        ])->assertForbidden();
        $this->get(route('modules.edit', $module))->assertForbidden();
        $this->put(route('modules.update', $module), [
            'title' => 'Invasão',
            'description' => 'Invasão',
        ])->assertForbidden();
        $this->delete(route('modules.destroy', $module))->assertForbidden();
        $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$module->id],
        ])->assertForbidden();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Invasão',
            'type' => 'content',
        ])->assertForbidden();
        $this->get(route('lessons.edit', $lesson))->assertForbidden();
        $this->put(route('lessons.update', $lesson), [
            'title' => 'Invasão',
            'type' => 'content',
        ])->assertForbidden();
        $this->delete(route('lessons.destroy', $lesson))->assertForbidden();
        $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$lesson->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('modules', ['id' => $module->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'deleted_at' => null]);
    }

    public function test_unassigned_professor_cannot_reorder_the_content_of_another_orgs_course(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $this->actingAs($professor);

        // The route-bound `Course` is OrgScope-filtered for a foreign-org
        // Professor, so the request dies on the binding (404) before any
        // policy runs — either way, never a successful write.
        $moduleResponse = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$module->id],
        ]);
        $lessonResponse = $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$lesson->id],
        ]);

        $this->assertGreaterThanOrEqual(400, $moduleResponse->getStatusCode());
        $this->assertGreaterThanOrEqual(400, $lessonResponse->getStatusCode());
        $this->assertSame(0, $module->fresh()->order_index);
    }

    public function test_professor_cannot_touch_the_course_crud(): void
    {
        $org = Organization::factory()->create();
        [, $course] = $this->assignedProfessor($org);

        // Course metadata stays `CoursePolicy`-gated: even an assigned
        // Professor is denied (and creating a Course of his own too).
        $this->get(route('courses.edit', $course))->assertForbidden();
        $this->put(route('courses.update', $course), [
            'title' => 'Hijacked',
        ])->assertForbidden();
        $this->post(route('courses.store'), [
            'title' => 'Curso do Professor',
            'workload_hours' => 10,
        ])->assertForbidden();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => $course->title]);
        $this->assertDatabaseMissing('courses', ['title' => 'Curso do Professor']);
    }

    public function test_gestor_of_the_same_org_still_creates_modules(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, 'gestor');

        $this->post(route('courses.modules.store', $course), [
            'title' => 'Módulo do Gestor',
            'description' => 'Regressão',
        ])->assertRedirect(route('courses.modules.index', $course));

        $this->assertDatabaseHas('modules', ['course_id' => $course->id, 'title' => 'Módulo do Gestor']);
    }
}
