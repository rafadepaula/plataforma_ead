<?php

namespace Tests\Browser\Theme;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Acceptance-criteria guardrail: the mobile drawer (Bootstrap Offcanvas,
 * `components/layout/sidebar.blade.php`) opens and closes at 375px width,
 * triggered declaratively from the topbar's `[data-bs-toggle="offcanvas"]`
 * button (`@mobile-menu-button`) — no project JS participates.
 */
class DrawerMobileTest extends DuskTestCase
{
    public function test_mobile_drawer_opens_and_closes_at_375px(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->resize(375, 812)
                ->visit(route('admin.dashboard'))
                ->waitFor('@mobile-menu-button')
                ->assertVisible('@mobile-menu-button')
                ->assertMissing('#mobile-sidebar.show');

            // Open: the offcanvas becomes visible.
            $browser->click('@mobile-menu-button')
                ->waitFor('#mobile-sidebar.show')
                ->assertVisible('#mobile-sidebar');

            // Close via its own dismiss button: the offcanvas hides again.
            $browser->click('#mobile-sidebar .btn-close')
                ->waitUntilMissing('#mobile-sidebar.show')
                ->waitUntilMissing('.offcanvas-backdrop');

            $browser->resize(1920, 1080);
        });
    }
}
