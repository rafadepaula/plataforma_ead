<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 *  the 4th platform role. Beyond the
 * Spatie `professor` role itself (enum value, human label, `User::roleLabel`),
 * the professor's perimeter is the `course_professor` pivot — not the
 * Organization: `User::teaches()` is the single access-decision helper and a
 * same-org professor without an assignment is still an outsider to a Course.
 * There is also no self-service entry point into the role: `role:professor`
 * routes are reachable only by an authenticated professor.
 */
class ProfessorRoleTest extends TestCase
{
    // ── Role enum & labels ───────────────────────────────────────────

    public function test_professor_is_the_fourth_role_with_a_human_label(): void
    {
        $this->assertSame('professor', RolesEnum::PROFESSOR->value);

        // `RolesAndPermissionsSeeder` iterates `RolesEnum::cases()`, so the
        // 4th role must be part of the seeded set by construction.
        $this->assertSame(
            ['admin', 'gestor', 'aluno', 'professor'],
            array_map(fn (RolesEnum $role): string => $role->value, RolesEnum::cases()),
        );

        $this->assertSame('Professor', RolesEnum::label(RolesEnum::PROFESSOR->value));
        $this->assertSame('Professor', RolesEnum::label('professor'));
    }

    public function test_a_professor_users_role_label_reads_professor(): void
    {
        $professor = User::factory()->professor()->create();

        $this->assertTrue($professor->hasRole(RolesEnum::PROFESSOR->value));
        $this->assertSame('Professor', $professor->roleLabel);
    }

    // ── Factory / tenant ─────────────────────────────────────────────

    public function test_professor_factory_keeps_an_explicitly_given_org(): void
    {
        $org = Organization::factory()->create();

        $professor = User::factory()->professor()->create(['org_id' => $org->id]);

        $this->assertSame($org->id, $professor->org_id);
        $this->assertSame($org->id, $professor->organization->id);
    }

    public function test_a_professor_created_without_an_explicit_org_gets_one(): void
    {
        // Mirrors `gestor()`: a professor belongs to exactly one
        // Organization, so the factory state auto-creates one instead of
        // leaving `org_id` null (the org-less default of `aluno`).
        $professor = User::factory()->professor()->create();

        $this->assertNotNull($professor->org_id);
        $this->assertInstanceOf(Organization::class, $professor->organization);
        $this->assertDatabaseHas('organizations', ['id' => $professor->org_id]);
    }

    // ── Course assignment pivot ──────────────────────────────────────

    public function test_a_professor_does_not_teach_a_course_they_were_never_assigned_to(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->for($org)->create();

        $this->assertFalse($professor->teaches($course));
        $this->assertSame(0, $professor->taughtCourses()->count());
        $this->assertSame(0, $course->professors()->count());
    }

    public function test_assigning_a_course_makes_the_professor_teach_it_and_records_the_assigner(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->for($org)->create();

        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $this->assertTrue($professor->teaches($course));
        $this->assertSame([$professor->id], $course->professors()->pluck('users.id')->all());

        $pivot = DB::table('course_professor')
            ->where('course_id', $course->id)
            ->where('user_id', $professor->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertSame($gestor->id, $pivot->assigned_by);
        $this->assertNotNull($pivot->created_at);
    }

    public function test_reassigning_the_same_course_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->for($org)->create();

        // `UNIQUE(course_id, user_id)` backs the idempotency: attaching the
        // same pair twice must neither duplicate the row nor blow up.
        $course->professors()->syncWithoutDetaching([$professor->id => ['assigned_by' => $gestor->id]]);
        $course->professors()->syncWithoutDetaching([$professor->id => ['assigned_by' => $gestor->id]]);

        $this->assertDatabaseCount('course_professor', 1);
        $this->assertTrue($professor->teaches($course));
    }

    public function test_a_duplicated_course_professor_pair_is_rejected_by_the_database(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->for($org)->create();
        $course->professors()->attach($professor->id);

        try {
            DB::table('course_professor')->insert([
                'course_id' => $course->id,
                'user_id' => $professor->id,
                'assigned_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected the UNIQUE(course_id, user_id) index to reject a duplicated pair.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('course_professor', 1);
    }

    // ── No self-service access to the professor surfaces ────────────

    public function test_a_guest_hitting_a_professor_route_is_redirected_to_login(): void
    {
        $this->get(route('professor.dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_aluno_is_forbidden_from_the_professor_routes(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $this->get(route('professor.dashboard'))->assertForbidden();
        $this->get(route('professor.courses.index'))->assertForbidden();
    }

    public function test_a_gestor_is_forbidden_from_the_professor_routes(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestor);

        $this->get(route('professor.dashboard'))->assertForbidden();
        $this->get(route('professor.courses.index'))->assertForbidden();
    }

    public function test_an_admin_is_forbidden_from_the_professor_routes(): void
    {
        $this->actingAsAdmin();

        $this->get(route('professor.dashboard'))->assertForbidden();
        $this->get(route('professor.courses.index'))->assertForbidden();
    }

    public function test_a_professor_reaches_their_own_dashboard(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->for($org)->create();
        $course->professors()->attach($professor->id);
        $this->actingAs($professor);

        $this->get(route('professor.dashboard'))
            ->assertOk()
            ->assertViewIs('professor.dashboard.index');
    }
}
