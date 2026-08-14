<?php

namespace Tests\Browser\Navigation;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UX-001 — E2E coverage of the Admin sidebar's scope split. In global
 * context the Admin only sees the system-administration surface; the
 * Organization-scoped items appear (and disappear) with the "Impersonate
 * Org" context, grouped under their own "Impersonate" heading.
 *
 * The whole rule lives in `NavigationRegistry`/`NavigationService`, so the
 * desktop `<aside>` and the mobile Offcanvas consume the same resolved
 * array — both renders are asserted here.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): o
 * mesmo Admin percorre contexto global → assumir Organização → link real →
 * encerrar contexto no desktop; o espelho no Offcanvas mobile é a mesma
 * jornada num viewport estreito.
 */
class AdminSidebarScopeTest extends DuskTestCase
{
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

    public function test_admin_sidebar_scope_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Organização Alvo']);
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            // 1. Contexto global: nenhum item de Organização, nenhuma seção
            //    "Impersonate", e o Admin não é aprendiz.
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertSee('Organizações')
                ->assertDontSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertMissing('@sidebar-'.$key.'-link')
                    ->assertMissing('@sidebar-'.$key.'-link-mobile');
            }

            $browser->assertMissing('@sidebar-student-courses-link')
                ->assertMissing('@sidebar-student-courses-link-mobile')
                ->assertDontSee('Meus Cursos')
                ->assertDontSee('APRENDIZADO');

            // 2. Assumir a Organização revela a seção inteira.
            $browser->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->waitFor('@sidebar-courses-link')
                ->assertSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertPresent('@sidebar-'.$key.'-link')
                    ->assertPresent('@sidebar-'.$key.'-link-mobile');
            }

            // 3. O link agrupado é real e alcançável (RF36).
            $browser->click('@sidebar-courses-link')
                ->waitForLocation('/courses')
                ->assertDontSee('Selecione uma Organização ativa antes de continuar.');

            // 4. Encerrar o contexto derruba a seção inteira de novo. UX-002
            //    §4.4 tornou o destino determinístico (`admin.dashboard`).
            $browser->visit(route('organizations.index'))
                ->waitFor('@exit-impersonation')
                ->click('@exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->waitUntilMissing('@sidebar-courses-link')
                ->assertDontSee(self::IMPERSONATE_HEADING);

            foreach (self::OPERATIONAL_KEYS as $key) {
                $browser->assertMissing('@sidebar-'.$key.'-link')
                    ->assertMissing('@sidebar-'.$key.'-link-mobile');
            }
        });
    }

    /**
     * UX-001 — o Offcanvas mobile consome exatamente o mesmo array
     * resolvido, então os dois menus sempre têm que concordar.
     */
    public function test_mobile_offcanvas_mirrors_the_desktop_scope_rules(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            // 1. Contexto global no viewport estreito.
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

            // 2. Com a Organização assumida pela UI real ("Entrar como" é um
            //    POST de formulário), o Offcanvas espelha o desktop.
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
                ->assertVisible('@sidebar-forum-moderation-link-mobile')
                ->resize(1920, 1080);
        });
    }
}
