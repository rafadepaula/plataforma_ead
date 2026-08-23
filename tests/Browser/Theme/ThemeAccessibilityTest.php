<?php

namespace Tests\Browser\Theme;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ThemeAccessibilityTest extends DuskTestCase
{
    public function test_theme_and_accessibility_full_lifecycle_chain(): void
    {
        $org = Organization::factory()->create(['name' => 'Conselho Regional de Farmácia']);
        $admin = User::factory()->create(['org_id' => null, 'name' => 'Administrador']);
        $admin->assignRole(RolesEnum::ADMIN->value);

        HelpArticle::factory()->global()->create([
            'target_page_key' => 'admin.dashboard',
            'title' => 'Ajuda do Painel Administrativo',
            'content' => 'Guia de uso do painel de administração.',
        ]);

        $this->browse(function (Browser $browser) use ($admin): void {
            // 1. Visit Login screen
            $browser->visit(route('login'))
                ->waitFor('@login-form')
                ->assertPresent('.guest-panel')
                ->assertPresent('.brand-mark');

            // 2. Login as Admin and visit Dashboard
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard');

            // 3. Check Appbar structure
            $browser->assertPresent('.appbar')
                ->assertPresent('.brand-mark')
                ->assertPresent('[dusk^="help-button-"]')
                ->assertPresent('.ds-avatar');

            // 4. Test Help modal open & dismiss
            $browser->click('[dusk^="help-button-"]')
                ->waitForModalShown('help-modal-admin-dashboard')
                ->assertSeeIn('[dusk^="help-modal-"]', 'Ajuda do Painel Administrativo')
                ->click('[dusk^="help-modal-"] [data-bs-dismiss="modal"]')
                ->waitForModalClosed('help-modal-admin-dashboard');
        });
    }
}
