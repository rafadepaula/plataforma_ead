<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-06 RF21 — Gestor/Admin panel for manually enrolling and revoking a
 * Course's `course_user` rows via `EnrollmentController`. Authorization is
 * against the parent `Course` (`CoursePolicy::update`) since `course_user`
 * is a pivot only — no dedicated `Enrollment` model/policy exists.
 */
class EnrollmentManagementTest extends TestCase
{
    public function test_gestor_can_view_the_enrollments_index_for_their_own_course(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->get(route('courses.enrollments.index', $course))
            ->assertOk()
            ->assertViewIs('courses.enrollments.index')
            ->assertSee($student->name);
    }

    public function test_gestor_can_manually_enroll_an_existing_user(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $response = $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ]);

        $response->assertRedirect(route('courses.enrollments.index', $course));
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_manually_enrolling_an_already_actively_enrolled_user_fails_validation(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');
    }

    public function test_manually_enrolling_a_previously_cancelled_user_reactivates_their_enrollment(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now()->subMonth(), 'status' => 'cancelled']);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('course_user', 1);
    }

    public function test_gestor_can_revoke_an_active_enrollment(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->delete(route('courses.enrollments.destroy', [$course, $student]))
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * `CoursePolicy`/`CourseController::destroy()`'s "no active
     * enrollments" guard (`Course::hasActiveEnrollments()`) must stay
     * consistent with `EnrollmentController::destroy()`'s revocation
     * semantics: revoking a Course's only active enrollment (setting
     * `status = 'cancelled'`, never detaching the pivot row) should make
     * the Course deletable again.
     */
    public function test_revoking_the_only_active_enrollment_makes_the_course_deletable(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->from(route('courses.enrollments.index', $course))
            ->delete(route('courses.destroy', $course))
            ->assertRedirect(route('courses.enrollments.index', $course))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($course);

        $this->delete(route('courses.enrollments.destroy', [$course, $student]))
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->delete(route('courses.destroy', $course))->assertRedirect();
        $this->assertSoftDeleted($course);
    }

    public function test_aluno_is_forbidden_from_the_enrollments_panel(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->get(route('courses.enrollments.index', $course))->assertForbidden();
        $this->post(route('courses.enrollments.store', $course), ['user_id' => 1])->assertForbidden();
    }

    public function test_gestor_from_another_org_cannot_manage_enrollments_of_a_course_they_do_not_own(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // `OrgScope` on `Course` hides the row entirely for a Gestor of a
        // different org, so route-model binding itself 404s before
        // authorization ever runs — same pattern as
        // `MultiTenantCourseManagementTest`'s cross-tenant Course checks.
        $this->get(route('courses.enrollments.index', $otherCourse))->assertNotFound();
        $this->post(route('courses.enrollments.store', $otherCourse), ['user_id' => 1])->assertNotFound();
    }

    public function test_gestor_cannot_manually_enroll_a_user_from_a_different_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->create(['org_id' => $otherOrg->id]);
        $outsider->assignRole(RolesEnum::ALUNO->value);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $outsider->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_user', [
            'user_id' => $outsider->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_gestor_cannot_manually_enroll_a_staff_account(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $anotherGestor = User::factory()->create(['org_id' => $org->id]);
        $anotherGestor->assignRole(RolesEnum::GESTOR->value);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $anotherGestor->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_user', [
            'user_id' => $anotherGestor->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_guest_is_redirected_away_from_the_enrollments_panel(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);

        $this->get(route('courses.enrollments.index', $course))->assertRedirect();
    }
}
