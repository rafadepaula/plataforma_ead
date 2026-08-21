<?php

namespace Tests\Browser\Navigation;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 *  E2E coverage of the topbar after the dead search field was
 * removed and the "Impersonate Org" badge took its place.
 *
 * The badge is rendered by `components/layout/topbar.blade.php` from the
 * `$activeOrganization` injected by `NavigationComposer`, so it is present
 * on *every* authenticated screen — asserted here on more than one route.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): um
 * único Admin percorre contexto global → assumir Organização → badge em
 * várias telas → viewport estreito → encerrar contexto.
 */
class AdminTopbarTest extends DuskTestCase
{
    /**
     * A caixa do badge é decisão de tema, não comportamento: as asserções
     * de texto aqui ignoram a caixa renderizada
     * ({@see DuskTestCase::registerCaseInsensitiveTextMacros()}).
     */
    private const ORG_NAME = 'Organização Alvo';

    private const ORG_NAME_RENDERED = self::ORG_NAME;

    public function test_admin_topbar_impersonation_badge_lifecycle(): void
    {
        $organization = Organization::factory()->create(['name' => self::ORG_NAME]);
        $admin = $this->systemAdmin();

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            // 1. Contexto global: sem badge, e o campo de busca morto continua
            //    fora de qualquer tela autenticada.
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@topbar-profile-link')
                ->assertMissing('input[placeholder="Buscar cursos, aulas..."]')
                ->assertMissing('@topbar-impersonation')
                ->assertMissing('@topbar-exit-impersonation')
                ->visit(route('organizations.index'))
                ->waitFor('@topbar-profile-link')
                ->assertMissing('input[placeholder="Buscar cursos, aulas..."]');

            // 2. Assumir a Organização acende o badge.
            $browser->waitFor('@impersonate-'.$organization->id)
                ->click('@impersonate-'.$organization->id)
                ->waitForLocation('/organizations')
                ->waitFor('@topbar-impersonation')
                ->assertSeeInIgnoringCase('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            // 3. O badge acompanha o Admin em telas sem relação com
            //    Organizações e em telas operacionais da Organização.
            $browser->visit(route('admin.dashboard'))
                ->waitFor('@topbar-impersonation')
                ->assertSeeInIgnoringCase('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            $browser->visit(route('courses.index'))
                ->waitFor('@topbar-impersonation')
                ->assertSeeInIgnoringCase('@topbar-active-org-badge', self::ORG_NAME_RENDERED);

            // 4. Viewport estreito: badge e controles do cluster à direita
            //    sobrevivem.
            $browser->resize(768, 900)
                ->visit(route('admin.dashboard'))
                ->waitFor('@topbar-impersonation')
                ->assertVisible('@topbar-active-org-badge')
                ->assertVisible('@topbar-exit-impersonation')
                ->assertVisible('@topbar-profile-link')
                ->assertVisible('@mobile-menu-button')
                ->resize(1920, 1080);

            // 5.  sair do contexto pelo topbar sempre cai no
            //    dashboard, nunca num `back()` para uma tela cujo conteúdo
            //    dependia do contexto recém-abandonado.
            $browser->visit(route('admin.dashboard'))
                ->waitFor('@topbar-exit-impersonation')
                ->click('@topbar-exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->waitUntilMissing('@topbar-impersonation')
                ->assertMissing('@topbar-exit-impersonation')
                // O nome da Organização segue legítimo no corpo da tela (a
                // tabela "Resumo das Organizações" do Admin global lista
                // todas), então o que precisa sumir é o badge, não o texto.
                ->assertMissing('@topbar-active-org-badge');
        });
    }

    private function systemAdmin(): User
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        return $admin;
    }
}
