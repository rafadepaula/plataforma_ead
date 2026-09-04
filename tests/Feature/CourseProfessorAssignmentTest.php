<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 *  the per-course Professor assignment panel (`courses.professors.*`):
 * `role:admin|gestor` + `CoursePolicy::update`, pivot-only
 * (`course_professor`, `assigned_by` auditor column, UNIQUE(course_id,
 * user_id) + `syncWithoutDetaching` for idempotency). A Professor is a
 * TARGET of this panel, never an actor on it.
 */
class CourseProfessorAssignmentTest extends TestCase
{
    private function courseWithGestor(): array
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->actingAs($gestor);

        return [$org, $gestor, $course];
    }

    public function test_gestor_sees_the_panel_listing_assigned_professors(): void
    {
        [$org, $gestor, $course] = $this->courseWithGestor();
        $professor = User::factory()->professor()->create([
            'org_id' => $org->id,
            'name' => 'Professora Atribuída',
        ]);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $this->get(route('courses.professors.index', $course))
            ->assertOk()
            ->assertViewIs('courses.professors.index')
            ->assertSee('Professora Atribuída')
            // The `assigned_by` auditor column is surfaced by name.
            ->assertSee($gestor->name);
    }

    public function test_admin_impersonating_the_org_also_sees_the_panel(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->attach($professor->id);

        // `actingAsAdmin()` seeds `session('active_org_id')`, the same
        // Impersonate Org context `OrgScope` reads on the `{course}` binding.
        $this->actingAsAdmin($org);

        $this->get(route('courses.professors.index', $course))
            ->assertOk()
            ->assertSee($professor->name);
    }

    public function test_store_attaches_a_same_org_professor_recording_assigned_by(): void
    {
        [$org, $gestor, $course] = $this->courseWithGestor();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);

        $this->post(route('courses.professors.store', $course), [
            'user_id' => $professor->id,
        ])->assertRedirect(route('courses.professors.index', $course))
            ->assertSessionHas('success', 'Professor atribuído ao curso com sucesso.');

        $this->assertDatabaseHas('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
            'assigned_by' => $gestor->id,
        ]);

        // Assignment is NOT enrollment: `course_user` must stay untouched.
        $this->assertDatabaseMissing('course_user', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
        ]);
    }

    public function test_store_rejects_a_professor_from_another_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $foreignProfessor = User::factory()->professor()->create(['org_id' => $otherOrg->id]);

        $this->actingAs($gestor);

        $this->from(route('courses.professors.index', $course))
            ->post(route('courses.professors.store', $course), [
                'user_id' => $foreignProfessor->id,
            ])
            ->assertRedirect(route('courses.professors.index', $course))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_professor', [
            'course_id' => $course->id,
            'user_id' => $foreignProfessor->id,
        ]);
    }

    public function test_store_rejects_a_same_org_user_who_is_not_a_professor(): void
    {
        [$org, , $course] = $this->courseWithGestor();
        $aluno = User::factory()->aluno()->create(['org_id' => $org->id]);

        $this->post(route('courses.professors.store', $course), [
            'user_id' => $aluno->id,
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_professor', [
            'course_id' => $course->id,
            'user_id' => $aluno->id,
        ]);
    }

    public function test_duplicate_assignment_never_creates_a_second_row(): void
    {
        [$org, , $course] = $this->courseWithGestor();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);

        $this->post(route('courses.professors.store', $course), ['user_id' => $professor->id])
            ->assertRedirect(route('courses.professors.index', $course));
        $this->post(route('courses.professors.store', $course), ['user_id' => $professor->id])
            ->assertRedirect(route('courses.professors.index', $course))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('course_professor', 1);
        $this->assertDatabaseHas('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
        ]);
    }

    public function test_destroy_detaches_the_professor(): void
    {
        [$org, $gestor, $course] = $this->courseWithGestor();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $this->delete(route('courses.professors.destroy', [$course, $professor]))
            ->assertRedirect(route('courses.professors.index', $course))
            ->assertSessionHas('success', 'Atribuição do professor removida com sucesso.');

        $this->assertDatabaseMissing('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
        ]);
    }

    public function test_professor_is_forbidden_on_the_assignment_panel(): void
    {
        [$org, $gestor, $course] = $this->courseWithGestor();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);
        $this->actingAs($professor);

        // `role:admin|gestor` — even an ASSIGNED Professor never manages the
        // pivot. Same-org binding resolves, so this is a deterministic 403.
        $this->get(route('courses.professors.index', $course))->assertForbidden();
        $this->post(route('courses.professors.store', $course), [
            'user_id' => $professor->id,
        ])->assertForbidden();
        $this->delete(route('courses.professors.destroy', [$course, $professor]))->assertForbidden();

        $this->assertDatabaseHas('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
        ]);
    }

    public function test_gestor_of_another_org_never_reaches_the_assignment_panel(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->attach($professor->id);
        $foreignGestor = User::factory()->gestor()->create(['org_id' => $otherOrg->id]);

        $this->actingAs($foreignGestor);

        // `Course`'s `OrgScope` filters the route binding for a foreign-org
        // Gestor, so these die on the binding (404) before `CoursePolicy`
        // could answer — either way, never a reachable panel.
        $this->get(route('courses.professors.index', $course))
            ->assertNotFound();
        $this->post(route('courses.professors.store', $course), [
            'user_id' => $professor->id,
        ])->assertNotFound();
        $this->delete(route('courses.professors.destroy', [$course, $professor]))
            ->assertNotFound();
    }
}
