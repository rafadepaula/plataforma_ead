<?php

namespace Tests\Feature\OrgScope;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-00 §3 — the OrgScope global scope must transparently isolate every
 * org-scoped query so a user from Org A never sees Org B's rows.
 */
class OrgScopeTenantIsolationTest extends TestCase
{
    public function test_org_bound_user_only_sees_their_own_organizations_courses(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->count(2)->create(['org_id' => $orgA->id]);
        Course::factory()->count(3)->create(['org_id' => $orgB->id]);

        $userFromOrgA = User::factory()->create(['org_id' => $orgA->id]);
        $userFromOrgA->assignRole('gestor');
        $this->actingAs($userFromOrgA);

        $this->assertCount(2, Course::all());
        $this->assertTrue(Course::all()->every(fn (Course $course) => $course->org_id === $orgA->id));
    }

    public function test_org_bound_user_cannot_find_another_orgs_course_by_id(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseInOrgB = Course::factory()->create(['org_id' => $orgB->id]);

        $userFromOrgA = User::factory()->create(['org_id' => $orgA->id]);
        $userFromOrgA->assignRole('gestor');
        $this->actingAs($userFromOrgA);

        $this->assertNull(Course::find($courseInOrgB->id));
    }

    public function test_org_less_non_admin_user_sees_no_scoped_rows(): void
    {
        $organization = Organization::factory()->create();
        Course::factory()->count(2)->create(['org_id' => $organization->id]);

        $orgLessUser = User::factory()->create(['org_id' => null]);
        $orgLessUser->assignRole('aluno');
        $this->actingAs($orgLessUser);

        $this->assertCount(0, Course::all());
    }

    public function test_guest_without_authenticated_user_is_not_scoped(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->create(['org_id' => $orgA->id]);
        Course::factory()->create(['org_id' => $orgB->id]);

        $this->assertCount(2, Course::all());
    }
}
