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
    public function test_it_has_a_users_relationship(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $organization->id]);

        $this->assertTrue($organization->users->contains($user));
    }

    public function test_it_has_a_courses_relationship(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);

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
