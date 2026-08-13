<?php

namespace Tests\Browser\Navigation;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UX-001 — E2E coverage of the Admin sidebar's scope split. In global
 * context the Admin only sees the system-administration surface; the
 * Organization-scoped items appear (and disappear) with the "Impersonate
 * Org" context, grouped under their own "Impersonate" heading.
 *
 * The whole rule lives in `NavigationRegistry`/`NavigationService`, so
 * the desktop `<aside>` and the mobile Offcanvas consume the same
 * resolved array — both renders are asserted here.
 */
class AdminSidebarScopeTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @var list<string> The Organization-scoped item keys (UX-001). */
    private const OPERATIONAL_KEYS = ['courses', 'quiz-attempts', 'forum-moderation'];

    /**
     * `.sidebar-section-title` carries `text-transform: uppercase`
     * (`resources/scss/components/_index.scss:17`), and Dusk asserts
     * against the browser's *rendered* text — so the heading reads
     * "IMPERSONATE" in the DOM even though the registry declares
     * "Impersonate".
     */
    private const IMPERSONATE_HEADING = 'IMPERSONATE';

    public function test_admin_without_impersonation_sees_no_organization_scoped_items(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertSee('Organizações')
                ->assertDontSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertMissing('@sidebar-'.$key.'-link')
                    ->assertMissing('@sidebar-'.$key.'-link-mobile');
            }

            // UX-001 — the Admin is not a learner.
            $browser->assertMissing('@sidebar-student-courses-link')
                ->assertMissing('@sidebar-student-courses-link-mobile')
                ->assertDontSee('Meus Cursos')
                ->assertDontSee('APRENDIZADO');
        });
    }

    public function test_impersonating_an_org_reveals_the_impersonate_section_and_hiding_it_again_on_exit(): void
    {
        $org = Organization::factory()->create(['name' => 'Organização Alvo']);
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->waitFor('@sidebar-courses-link')
                ->assertSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertPresent('@sidebar-'.$key.'-link')
                    ->assertPresent('@sidebar-'.$key.'-link-mobile');
            }

            // The grouped link is real and reachable (RF36).
            $browser->click('@sidebar-courses-link')
                ->waitForLocation('/courses')
                ->assertDontSee('Selecione uma Organização ativa antes de continuar.');

            // Ending the context drops the whole section again.
            $browser->visit(route('organizations.index'))
                ->waitFor('@exit-impersonation')
                ->click('@exit-impersonation')
                ->waitForLocation('/organizations')
                ->waitUntilMissing('@sidebar-courses-link')
                ->assertDontSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertMissing('@sidebar-'.$key.'-link')
                    ->assertMissing('@sidebar-'.$key.'-link-mobile');
            }
        });
    }

    /**
     * UX-001 — the mobile Offcanvas consumes the very same resolved
     * array, so the desktop and mobile menus must always agree.
     */
    public function test_mobile_offcanvas_mirrors_the_desktop_scope_rules(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            $browser->loginAs($admin)
                ->resize(390, 844)
                ->visit(route('admin.dashboard'))
                ->waitFor('@mobile-menu-button')
                ->click('@mobile-menu-button')
                ->waitFor('#mobile-sidebar.show')
                ->assertSee('Organizações')
                ->assertDontSee(self::IMPERSONATE_HEADING)
                ->assertMissing('@sidebar-courses-link-mobile')
                ->assertMissing('@sidebar-student-courses-link-mobile');

            // Now with an Organization impersonated (via the real UI —
            // "Entrar como" is a POST form).
            $browser->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->visit(route('admin.dashboard'))
                ->waitFor('@mobile-menu-button')
                ->click('@mobile-menu-button')
                ->waitFor('#mobile-sidebar.show')
                ->assertSee(self::IMPERSONATE_HEADING)
                ->assertVisible('@sidebar-courses-link-mobile')
                ->assertVisible('@sidebar-quiz-attempts-link-mobile')
                ->assertVisible('@sidebar-forum-moderation-link-mobile');
        });
    }
}
