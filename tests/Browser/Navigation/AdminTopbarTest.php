<?php

namespace Tests\Browser\Navigation;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UX-002 — E2E coverage of the topbar after the dead search field was
 * removed and the "Impersonate Org" badge took its place.
 *
 * The badge is rendered by `components/layout/topbar.blade.php` from the
 * `$activeOrganization` injected by `NavigationComposer`, so it is
 * present on *every* authenticated screen — asserted here on more than
 * one route.
 */
class AdminTopbarTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * `.badge` carries `text-transform: uppercase`
     * (`resources/scss/components/_index.scss:57`) and Dusk asserts
     * against the browser's *rendered* text — so the Organization name
     * reads in caps in the DOM even though the model stores
     * "Organização Alvo". Same convention as
     * {@see AdminSidebarScopeTest::IMPERSONATE_HEADING}.
     */
    private const ORG_NAME = 'Organização Alvo';

    private const ORG_NAME_RENDERED = 'ORGANIZAÇÃO ALVO';

    public function test_the_dead_search_field_is_gone_from_authenticated_screens(): void
    {
        $admin = $this->systemAdmin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@topbar-profile-link')
                ->assertMissing('input[placeholder="Buscar cursos, aulas..."]')
                ->visit(route('organizations.index'))
                ->waitFor('@topbar-profile-link')
                ->assertMissing('input[placeholder="Buscar cursos, aulas..."]');
        });
    }

    public function test_no_impersonation_badge_is_rendered_in_global_context(): void
    {
        $admin = $this->systemAdmin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@topbar-profile-link')
                ->assertMissing('@topbar-impersonation')
                ->assertMissing('@topbar-exit-impersonation');
        });
    }

    public function test_the_badge_follows_the_admin_across_screens_and_exits_to_the_dashboard(): void
    {
        $organization = Organization::factory()->create(['name' => self::ORG_NAME]);
        $admin = $this->systemAdmin();

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$organization->id)
                ->click('@impersonate-'.$organization->id)
                ->waitForLocation('/organizations')
                ->waitFor('@topbar-impersonation')
                ->assertSeeIn('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            // Screen 2: a route that has nothing to do with Organizations.
            $browser->visit(route('admin.dashboard'))
                ->waitFor('@topbar-impersonation')
                ->assertSeeIn('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            // Screen 3: an Organization-scoped operational screen.
            $browser->visit(route('courses.index'))
                ->waitFor('@topbar-impersonation')
                ->assertSeeIn('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            // UX-002 §4.4 — leaving the context from the topbar always
            // lands on the dashboard, never `back()` into a screen whose
            // content depended on the context just dropped.
            $browser->click('@topbar-exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->waitUntilMissing('@topbar-impersonation')
                ->assertMissing('@topbar-exit-impersonation')
                ->assertDontSee(self::ORG_NAME_RENDERED);
        });
    }

    /**
     * The badge shares the right-hand cluster with the profile/logout
     * controls, which must survive a narrow viewport.
     */
    public function test_the_topbar_controls_survive_a_narrow_viewport_with_the_badge_present(): void
    {
        $organization = Organization::factory()->create(['name' => self::ORG_NAME]);
        $admin = $this->systemAdmin();

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$organization->id)
                ->click('@impersonate-'.$organization->id)
                ->waitForLocation('/organizations')
                ->resize(768, 900)
                ->visit(route('admin.dashboard'))
                ->waitFor('@topbar-impersonation')
                ->assertVisible('@topbar-active-org-badge')
                ->assertVisible('@topbar-exit-impersonation')
                ->assertVisible('@topbar-profile-link')
                ->assertVisible('@mobile-menu-button');
        });
    }

    private function systemAdmin(): User
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        return $admin;
    }
}
