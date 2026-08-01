<?php

namespace Tests\Feature\OrgScope;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-00 §3 — Admins have `org_id = null` and are queried across every
 * organization by default, but MUST scope down to a single Organization
 * once they "Impersonate Org" via `session('active_org_id')`.
 */
class OrgScopeImpersonateOrgTest extends TestCase
{
    public function test_admin_without_impersonation_sees_courses_from_every_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->create(['org_id' => $orgA->id]);
        Course::factory()->create(['org_id' => $orgB->id]);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertCount(2, Course::all());
    }

    public function test_admin_impersonating_org_only_sees_that_orgs_courses(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->create(['org_id' => $orgA->id]);
        Course::factory()->create(['org_id' => $orgB->id]);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->withSession(['active_org_id' => $orgA->id]);

        $courses = Course::all();

        $this->assertCount(1, $courses);
        $this->assertSame($orgA->id, $courses->first()->org_id);
    }

    public function test_switching_impersonated_org_changes_the_visible_courses(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Course::factory()->create(['org_id' => $orgA->id]);
        Course::factory()->create(['org_id' => $orgB->id]);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->withSession(['active_org_id' => $orgA->id]);
        $this->assertSame($orgA->id, Course::sole()->org_id);

        $this->withSession(['active_org_id' => $orgB->id]);
        $this->assertSame($orgB->id, Course::sole()->org_id);
    }

    public function test_records_created_while_impersonating_org_receive_that_orgs_id(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->withSession(['active_org_id' => $organization->id]);

        $course = Course::factory()->make(['org_id' => null]);
        $course->save();

        $this->assertSame($organization->id, $course->fresh()->org_id);
    }
}
