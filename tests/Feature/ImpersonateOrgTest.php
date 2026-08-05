<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use Tests\TestCase;

/**
 * SPEC-04 §2 / UC18 — Admin can set/clear `session('active_org_id')` via
 * `ImpersonateOrgController`, which `OrgScope` then reads to filter data.
 */
class ImpersonateOrgTest extends TestCase
{
    public function test_admin_can_set_the_active_org_id(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $this->post(route('impersonate-org.store', $organization))
            ->assertRedirect();

        $this->assertSame($organization->id, session('active_org_id'));
    }

    public function test_admin_can_clear_the_active_org_id(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsAdmin($organization);

        $this->assertSame($organization->id, session('active_org_id'));

        $this->delete(route('impersonate-org.destroy'))
            ->assertRedirect();

        $this->assertNull(session('active_org_id'));
    }

    public function test_admin_cannot_impersonate_an_inactive_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->inactive()->create();

        $this->post(route('impersonate-org.store', $organization))
            ->assertSessionHasErrors();

        $this->assertNull(session('active_org_id'));
    }

    public function test_admin_cannot_impersonate_a_nonexistent_organization(): void
    {
        $this->actingAsAdmin();

        $this->post(route('impersonate-org.store', 999999))
            ->assertNotFound();
    }

    public function test_gestor_is_forbidden_from_impersonating_an_org(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsOrgUser(role: 'gestor');

        $this->post(route('impersonate-org.store', $organization))
            ->assertForbidden();
    }

    public function test_aluno_is_forbidden_from_impersonating_an_org(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsOrgUser(role: 'aluno');

        $this->post(route('impersonate-org.store', $organization))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_when_trying_to_impersonate_an_org(): void
    {
        $organization = Organization::factory()->create();

        $this->post(route('impersonate-org.store', $organization))
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_org_scope_reflects_the_active_org_id_change_immediately(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $organizationA->id]);
        $courseB = Course::factory()->create(['org_id' => $organizationB->id]);

        $this->actingAsAdmin();

        $this->post(route('impersonate-org.store', $organizationA));

        $this->assertTrue(Course::query()->find($courseA->id) !== null);
        $this->assertNull(Course::query()->find($courseB->id));

        $this->post(route('impersonate-org.store', $organizationB));

        $this->assertNull(Course::query()->find($courseA->id));
        $this->assertTrue(Course::query()->find($courseB->id) !== null);
    }
}
