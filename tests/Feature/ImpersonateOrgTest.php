<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Organization;
use Tests\TestCase;

/**
 * Admin can set/clear `session('active_org_id')` via
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

    /**
     * UX-002 — the impersonation signal must persist across the whole
     * navigation, not only on `/organizations`: the topbar is rendered
     * by `layouts.app` on every authenticated screen.
     */
    public function test_topbar_shows_the_active_organization_on_every_authenticated_screen(): void
    {
        $organization = Organization::factory()->create(['name' => 'Instituto Contexto']);
        $this->actingAsAdmin($organization);

        foreach ([route('admin.dashboard'), route('organizations.index')] as $url) {
            $response = $this->get($url);

            $response->assertOk()
                ->assertSee('dusk="topbar-impersonation"', false)
                ->assertSee('dusk="topbar-exit-impersonation"', false)
                ->assertSee('Instituto Contexto');
        }
    }

    public function test_topbar_hides_the_impersonation_badge_for_an_admin_in_global_context(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('dusk="topbar-impersonation"', false)
            ->assertDontSee('dusk="topbar-exit-impersonation"', false);
    }

    /**
     * UX-002 §4.5 — a Gestor is permanently bound to its own `org_id`
     * and never impersonates, so the badge must never leak to it.
     */
    public function test_topbar_hides_the_impersonation_badge_for_a_gestor(): void
    {
        $organization = Organization::factory()->create(['name' => 'Instituto do Gestor']);
        $this->actingAsOrgUser($organization, role: 'gestor');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('dusk="topbar-impersonation"', false);
    }

    /**
     * UX-002 — failure path: the session still points at an Organization
     * that no longer exists (deleted while impersonated). The topbar must
     * degrade to "no badge" instead of blowing up on a null name.
     */
    public function test_topbar_survives_a_stale_active_org_id(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsAdmin($organization);
        $organization->forceDelete();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('dusk="topbar-impersonation"', false);
    }

    /**
     * UX-002 §4.1 — the decorative search field had no `<form>`, no
     * `name`, no `action` and no JS handler; it must be gone everywhere.
     */
    public function test_no_authenticated_screen_renders_the_dead_search_field(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Buscar cursos, aulas...');
    }

    /**
     * UX-002 §4.4 — with a global control, `back()` could return the
     * Admin to a screen whose content depended on the context just
     * dropped. The destination is now deterministic.
     */
    public function test_ending_the_impersonation_redirects_to_the_admin_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsAdmin($organization);

        $this->delete(route('impersonate-org.destroy'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNull(session('active_org_id'));
    }

    /**
     * Non-regression: changing the redirect must not
     * disturb the audit trail.
     */
    public function test_ending_the_impersonation_is_still_audited(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsAdmin($organization);

        $this->delete(route('impersonate-org.destroy'));

        $this->assertNotNull(
            AuditLog::withoutGlobalScopes()->where('event', 'impersonate.stop')->first()
        );
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
