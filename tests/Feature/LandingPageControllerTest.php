<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * Per-tenant landing contract (`GET /`, `landing.show`,
 * `LandingPageController`). The landing is the Organization's own blade
 * (`tenants.{landing_view}.landing`) rendered with the org's brand;
 * anything else — a portal without a usable blade or the state-zero host —
 * hands the visitor to the login. An inactive Organization's landing stays
 * reachable (view-only).
 */
class LandingPageControllerTest extends TestCase
{
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create([
            'name' => 'Liga Paulista de Kaioke',
            'landing_view' => 'ligacerto',
        ]);
        $this->onHost($this->org->host);
    }

    public function test_landing_page_returns_ok_for_guest_and_every_authenticated_role(): void
    {
        $this->get(route('landing.show'))->assertOk();

        $student = User::factory()->aluno()->inOrg($this->org)->create();
        $this->actingAs($student)->get(route('landing.show'))->assertOk();

        $manager = User::factory()->gestor()->inOrg($this->org)->create();
        $this->actingAs($manager)->get(route('landing.show'))->assertOk();

        // an Admin visiting the org's portal sees the landing too
        $admin = User::factory()->inOrg(null)->create();
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin)->get(route('landing.show'))->assertOk();
    }

    public function test_landing_renders_the_tenant_blade_with_the_org_brand(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertViewIs('tenants.ligacerto.landing');
        $response->assertViewHas('organization', fn (Organization $organization): bool => $organization->id === $this->org->id);
        $this->assertStringContainsString('Liga Paulista de Kaioke', $response->getContent());
    }

    public function test_guest_cta_and_footer_link_the_login_and_the_certificate_validation(): void
    {
        $content = $this->get(route('landing.show'))->getContent();

        $this->assertStringContainsString('href="'.route('login').'"', $content);
        $this->assertStringContainsString('href="'.route('certificates.verify').'"', $content);
    }

    public function test_an_authenticated_aluno_cta_points_at_the_course_catalog(): void
    {
        $student = User::factory()->aluno()->inOrg($this->org)->create();

        $content = $this->actingAs($student)->get(route('landing.show'))->getContent();

        $this->assertStringContainsString('href="'.route('student.courses.index').'"', $content);
    }

    public function test_a_portal_without_a_usable_landing_blade_redirects_guests_to_login(): void
    {
        $this->onHost(Organization::factory()->create()->host);

        $this->get(route('landing.show'))->assertRedirect(route('login'));
    }

    public function test_state_zero_redirects_guests_to_login(): void
    {
        $this->onHost(null);

        $this->get(route('landing.show'))->assertRedirect(route('login'));
    }

    public function test_an_inactive_orgs_landing_stays_reachable(): void
    {
        $this->org->forceFill(['status' => 'inactive'])->save();

        $this->get(route('landing.show'))->assertOk();
    }
}
