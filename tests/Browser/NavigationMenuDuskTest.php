<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Navigation\AdminSidebarScopeTest;
use Tests\DuskTestCase;

/**
 * SPEC-17 §4 — E2E coverage of the dynamic navigation menu: each role
 * logs in, opens an authenticated page rendered inside the app shell
 * (which mounts `components.layout.sidebar`/`topbar`), and Dusk verifies
 * the expected sidebar links are present/absent in the live DOM plus the
 * active-highlight behaviour on a sub-route. Mirrors the Feature-level
 * `RoleMenuVisibilityTest` but drives a real browser.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): há uma
 * cadeia por ator (Admin, Gestor, Aluno), porque a navegação é justamente
 * função do ator e do contexto — não uma tela isolada por módulo.
 */
class NavigationMenuDuskTest extends DuskTestCase
{
    /**
     * UX-001 / BUG-005 — cadeia do Admin: em contexto global o sidebar é a
     * superfície de administração *de sistema* apenas; depois de "Entrar
     * como", o item operacional de usuários volta e realmente funciona.
     * O detalhamento por seção do sidebar vive em
     * {@see AdminSidebarScopeTest}.
     */
    public function test_admin_navigation_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Da Org']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $org, $aluno): void {
            // 1. Contexto global: só navegação de sistema.
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertPresent('@sidebar-dashboard-link')
                ->assertPresent('@sidebar-organizations-link')
                ->assertPresent('@sidebar-audit-logs-link')
                ->assertPresent('@sidebar-settings-link')
                // BUG-005 — `Alunos & Usuários` é single-org e portanto
                // inalcançável para um Admin em contexto global.
                ->assertMissing('@sidebar-users-link')
                ->assertMissing('@sidebar-users-link-mobile');

            // 2. Assumir o contexto da Organização devolve o item operacional
            //    (desktop + Offcanvas mobile).
            $browser->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->waitFor('@sidebar-users-link')
                ->assertPresent('@sidebar-users-link-mobile');

            // 3. O link é real: clicar leva à listagem renderizada, sem
            //    ricochetear no guard de contexto.
            $browser->click('@sidebar-users-link')
                ->waitForLocation('/users')
                ->waitFor('@user-row-'.$aluno->id)
                ->assertDontSee('Selecione uma Organização ativa antes de continuar.')
                ->assertSee('Aluno Da Org');

            // 4. Encerrar o contexto esconde o item de novo (UX-002 §4.4 —
            //    destino determinístico no dashboard).
            $browser->visit(route('organizations.index'))
                ->waitFor('@exit-impersonation')
                ->click('@exit-impersonation')
                ->waitForLocation('/admin/dashboard')
                ->waitUntilMissing('@sidebar-users-link')
                ->assertMissing('@sidebar-users-link-mobile');
        });
    }

    public function test_gestor_navigation_scope(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertMissing('@sidebar-organizations-link')
                ->assertPresent('@sidebar-dashboard-link')
                ->assertPresent('@sidebar-users-link')
                ->assertPresent('@sidebar-settings-link');
        });
    }

    /**
     * RF39 — o Aluno não tem nenhum link administrativo, e o link do fórum
     * aparece apenas quando existe matrícula: as duas metades são o mesmo
     * ator, então são etapas da mesma cadeia (a matrícula é criada entre
     * elas e a tela é recarregada).
     */
    public function test_aluno_navigation_scope_and_enrolled_forum_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($aluno, $course): void {
            // 1. Sem matrícula: nenhum link administrativo, nem o do fórum.
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@no-enrollments')
                ->assertMissing('@sidebar-dashboard-link')
                ->assertMissing('@sidebar-organizations-link')
                ->assertMissing('@sidebar-users-link')
                ->assertMissing('@sidebar-courses-link')
                ->assertMissing('@sidebar-quiz-attempts-link')
                ->assertMissing('@sidebar-forum-moderation-link')
                ->assertMissing('@sidebar-audit-logs-link')
                ->assertMissing('@sidebar-settings-link')
                ->assertMissing('@sidebar-forum-link');

            // 2. Com matrícula ativa: o link contextual do fórum aparece.
            $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
            $this->assertDatabaseHas('course_user', [
                'user_id' => $aluno->id,
                'course_id' => $course->id,
                'status' => 'active',
            ]);

            $browser->visit(route('student.courses.index'))
                ->waitFor('@open-classroom-'.$course->id)
                ->assertPresent('@sidebar-forum-link')
                // Continua sem qualquer superfície administrativa.
                ->assertMissing('@sidebar-audit-logs-link')
                ->assertMissing('@sidebar-settings-link');
        });
    }

    public function test_active_item_highlight_is_applied_on_a_sub_route(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        // A Gestor always resolves a tenant context, so the users item is
        // visible for them (BUG-005 only hides it for a context-less Admin).
        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('users.create'))
                ->waitFor('@user-form');

            // The parent "Alunos & Usuários" item must carry the
            // `active` class on its `users.create` sub-route (RF37).
            // Dusk's `assertAttribute` does strict equality on the full
            // attribute string, so read the class and assert `active`
            // membership directly.
            $class = $browser->attribute('@sidebar-users-link', 'class');
            $this->assertNotNull($class, 'sidebar-users-link element is missing.');
            $this->assertStringContainsString(
                'active',
                $class,
                "Expected sidebar-users-link to carry the 'active' class on its users.create sub-route (RF37)."
            );
        });
    }
}
