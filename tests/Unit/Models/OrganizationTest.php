<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * `organizations` is the master tenant table and uses
 * `SoftDeletes`.
 */
class OrganizationTest extends TestCase
{
    public function test_it_has_a_memberships_relationship_to_the_org_accounts(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrg($organization->id)->create();

        // a pessoa conecta-se à org via sua conta (credential), não mais via users.org_id
        $this->assertTrue($organization->memberships->contains('user_id', $user->id));
    }

    public function test_it_has_a_courses_relationship(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->inOrg($organization->id)->create();

        $this->assertTrue($organization->courses->contains($course));
    }

    public function test_deleting_it_is_a_soft_delete(): void
    {
        $organization = Organization::factory()->create();

        $organization->delete();

        $this->assertSoftDeleted($organization);
        $this->assertNotNull($organization->fresh()->deleted_at);
    }

    public function test_default_status_is_active(): void
    {
        $organization = Organization::factory()->create();

        $this->assertSame('active', $organization->status);
    }
}
