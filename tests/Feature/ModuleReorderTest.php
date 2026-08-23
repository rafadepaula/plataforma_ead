<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use Tests\TestCase;

/**
 *  the AJAX/jQuery-wording module reorder endpoint. Persists a dense
 * `0..n-1` `order_index` sequence and rejects module ids that don't belong
 * to the route-bound `{course}` (cross-tenant/cross-course ID guessing).
 */
class ModuleReorderTest extends TestCase
{
    public function test_reorder_persists_the_new_order_index_sequence(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $first = Module::factory()->for($course)->create(['order_index' => 0]);
        $second = Module::factory()->for($course)->create(['order_index' => 1]);
        $third = Module::factory()->for($course)->create(['order_index' => 2]);

        $response = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $third->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
        $this->assertSame(2, $second->fresh()->order_index);
    }

    public function test_reorder_rejects_a_module_id_from_another_course(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $otherCourse = Course::factory()->create(['org_id' => $org->id]);
        $ownModule = Module::factory()->for($course)->create(['order_index' => 0]);
        $foreignModule = Module::factory()->for($otherCourse)->create(['order_index' => 0]);

        $response = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$ownModule->id, $foreignModule->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $ownModule->fresh()->order_index);
    }

    public function test_reorder_rejects_duplicate_module_ids(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $first = Module::factory()->for($course)->create(['order_index' => 0]);
        $second = Module::factory()->for($course)->create(['order_index' => 1]);

        // `ordered_ids.*` only validates existence, so `[a, a, b]` passes the
        // FormRequest; the controller's whereIn+count comparison (`2 !== 3`)
        // is what must reject it. Otherwise a duplicate id would write two
        // different `order_index` values to the same row (last write wins)
        // and leave the sibling with a stale index.
        $response = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$first->id, $first->id, $second->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $first->fresh()->order_index);
        $this->assertSame(1, $second->fresh()->order_index);
    }

    public function test_reorder_reassigns_a_dense_zero_based_sequence_from_sparse_indexes(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        // Simulate the historical sparse state (deletes without re-densify,
        // legacy imports): 0, 5, 9 instead of 0, 1, 2.
        $first = Module::factory()->for($course)->create(['order_index' => 0]);
        $second = Module::factory()->for($course)->create(['order_index' => 5]);
        $third = Module::factory()->for($course)->create(['order_index' => 9]);

        $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$first->id, $second->id, $third->id],
        ])->assertOk();

        $this->assertSame(0, $first->fresh()->order_index);
        $this->assertSame(1, $second->fresh()->order_index);
        $this->assertSame(2, $third->fresh()->order_index);
    }

    /**
     * The keyboard-accessible move-up/move-down buttons (SPEC-23 §4) swap a
     * single adjacent pair client-side, then POST the exact same payload the
     * drag-and-drop `drop` handler sends: the full `ordered_ids` array with
     * that one swap applied. They hit the identical endpoint, so the
     * whereIn+count cross-tenant guard cannot be bypassed by the button
     * path. This pins that contract at the HTTP layer.
     */
    public function test_move_up_button_payload_of_one_adjacent_swap_persists_dense_sequence(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $first = Module::factory()->for($course)->create(['order_index' => 0]);
        $second = Module::factory()->for($course)->create(['order_index' => 1]);
        $third = Module::factory()->for($course)->create(['order_index' => 2]);

        $response = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$first->id, $third->id, $second->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $first->fresh()->order_index);
        $this->assertSame(2, $second->fresh()->order_index);
        $this->assertSame(1, $third->fresh()->order_index);
    }

    public function test_reorder_rejects_a_module_id_from_another_orgs_course(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        // Fixtures are created before `actingAsOrgUser()` so `OrgScope`'s
        // `creating` hook doesn't overwrite the explicit `org_id` with the
        // (not-yet-authenticated) acting user's own tenant.
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create(['order_index' => 0]);
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $foreignModule = $otherCourse->modules()->create(['title' => 'Alheio', 'order_index' => 0]);

        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$module->id, $foreignModule->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_aluno_cannot_reorder_modules(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->postJson(route('modules.reorder', $course), [
            'ordered_ids' => [$module->id],
        ])->assertForbidden();
    }

    public function test_gestor_can_view_the_module_management_screens(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create(['title' => 'Módulo Editável']);

        $this->get(route('courses.modules.create', $course))
            ->assertOk()
            ->assertViewIs('courses.modules.create');

        $this->get(route('modules.edit', $module))
            ->assertOk()
            ->assertViewIs('courses.modules.edit');
    }

    public function test_reorder_lessons_persists_the_new_order_index_sequence(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $first = Lesson::factory()->for($module)->create(['order_index' => 0]);
        $second = Lesson::factory()->for($module)->create(['order_index' => 1]);

        $response = $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }

    public function test_reorder_lessons_rejects_a_lesson_id_from_another_module(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $otherModule = Module::factory()->for($course)->create();
        $ownLesson = Lesson::factory()->for($module)->create(['order_index' => 0]);
        $foreignLesson = Lesson::factory()->for($otherModule)->create(['order_index' => 0]);

        $response = $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$ownLesson->id, $foreignLesson->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_aluno_cannot_reorder_lessons(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->postJson(route('lessons.reorder', $module), [
            'ordered_ids' => [$lesson->id],
        ])->assertForbidden();
    }
}
