<?php

namespace Tests\Feature\OrgScope;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * the OrgScope global scope must transparently isolate every
 * org-scoped query so a user from Org A never sees Org B's rows.
 */
class OrgScopeTenantIsolationTest extends TestCase
{
    public function test_org_bound_user_only_sees_their_own_organizations_courses(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->count(2)->inOrg($orgA->id)->create();
        Course::factory()->count(3)->inOrg($orgB->id)->create();

        $userFromOrgA = User::factory()->inOrg($orgA->id)->create();
        $userFromOrgA->assignRole('gestor');
        $this->actingAs($userFromOrgA);
        $this->withOrgContext($orgA);

        $this->assertCount(2, Course::all());
        $this->assertTrue(Course::all()->every(fn (Course $course) => $course->org_id === $orgA->id));
    }

    public function test_org_bound_user_cannot_find_another_orgs_course_by_id(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseInOrgB = Course::factory()->inOrg($orgB->id)->create();

        $userFromOrgA = User::factory()->inOrg($orgA->id)->create();
        $userFromOrgA->assignRole('gestor');
        $this->actingAs($userFromOrgA);

        $this->assertNull(Course::find($courseInOrgB->id));
    }

    public function test_org_less_non_admin_user_sees_no_scoped_rows(): void
    {
        $organization = Organization::factory()->create();
        Course::factory()->count(2)->inOrg($organization->id)->create();

        $orgLessUser = User::factory()->inOrg(null)->create();
        $orgLessUser->assignRole('aluno');
        $this->actingAs($orgLessUser);

        $this->assertCount(0, Course::all());
    }

    public function test_guest_without_authenticated_user_is_not_scoped(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->inOrg($orgA->id)->create();
        Course::factory()->inOrg($orgB->id)->create();

        $this->assertCount(2, Course::all());
    }
}
