<?php

namespace Tests\Feature\OrgScope;

use App\Exceptions\UnresolvedOrgContextException;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-00 §3 — the mandatory guardrail test for the OrgScope trait's
 * `booted::creating` hook: creating a scoped model must never silently
 * persist `org_id = null` when neither the acting user's `org_id` nor
 * `session('active_org_id')` can resolve a tenant.
 */
class OrgScopeUnresolvedContextTest extends TestCase
{
    public function test_admin_without_active_org_context_throws_when_creating_scoped_model(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->expectException(UnresolvedOrgContextException::class);

        Course::factory()->make()->save();
    }

    public function test_org_less_non_admin_user_throws_when_creating_scoped_model(): void
    {
        // e.g. an "aluno" that has not yet enrolled/joined any organization.
        $orgLessUser = User::factory()->create(['org_id' => null]);
        $orgLessUser->assignRole('aluno');
        $this->actingAs($orgLessUser);

        $this->expectException(UnresolvedOrgContextException::class);

        Course::factory()->make()->save();
    }

    public function test_admin_with_active_org_context_does_not_throw(): void
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

    public function test_org_bound_user_does_not_throw_and_org_id_is_auto_assigned(): void
    {
        $organization = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $organization->id]);
        $gestor->assignRole('gestor');
        $this->actingAs($gestor);

        $course = Course::factory()->make(['org_id' => null]);
        $course->save();

        $this->assertSame($organization->id, $course->fresh()->org_id);
    }
}
