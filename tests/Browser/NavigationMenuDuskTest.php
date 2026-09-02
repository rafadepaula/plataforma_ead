<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Navigation\AdminTopbarTest;
use Tests\DuskTestCase;

/**
 * E2E coverage of the dynamic navigation menu: each role
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
     * cadeia do Admin: em contexto global o sidebar é a
     * superfície de administração *de sistema* apenas; depois de "Entrar
     * como", o item operacional de usuários volta e realmente funciona.
     * O detalhamento por seção do sidebar vive neste próprio ciclo de vida;
     * o cluster direito da topbar tem cobertura à parte em
     * {@see AdminTopbarTest}.
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
                //  `Alunos & Usuários` é single-org e portanto
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

            // 4. Encerrar o contexto esconde o item de novo (
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
                //  `users` (Alunos & Usuários) e `audit-logs`
                // (Auditoria) são exclusivos do Admin agora.
                ->assertMissing('@sidebar-users-link')
                ->assertMissing('@sidebar-audit-logs-link')
                ->assertPresent('@sidebar-dashboard-link')
                ->assertPresent('@sidebar-students-link')
                ->assertPresent('@sidebar-settings-link')
                // O link do diretório de Alunos é real: leva à listagem.
                ->click('@sidebar-students-link')
                ->waitForLocation('/gestor/students')
                ->waitFor('@gestor-students-index');
        });
    }

    /**
     *  o Aluno não tem nenhum link administrativo, e o link do fórum
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
                ->waitFor('@course-card-'.$course->id)
                ->assertPresent('@sidebar-forum-link')
                // Continua sem qualquer superfície administrativa.
                ->assertMissing('@sidebar-audit-logs-link')
                ->assertMissing('@sidebar-settings-link');

            // 3.  o atalho do curso matriculado aparece como filho
            // sempre-visível de "Meus Cursos" e leva à sala de aula, onde
            // ele mesmo fica destacado (binding `{course}` da rota).
            $browser->assertPresent('@sidebar-course-'.$course->id.'-link')
                ->assertPresent('@sidebar-see-all-link')
                ->click('@sidebar-course-'.$course->id.'-link')
                ->waitForLocation('/courses/'.$course->id.'/classroom');

            $childClass = $browser->attribute('@sidebar-course-'.$course->id.'-link', 'class');
            $this->assertStringContainsString(
                'active',
                (string) $childClass,
                "Expected sidebar-course-{$course->id}-link to carry the 'active' class inside its own classroom."
            );

            // 4. Mobile: o mesmo atalho dentro do Offcanvas leva à
            //    sala de aula também.
            $browser->resize(375, 812)
                ->visit(route('student.courses.index'))
                ->waitFor('@course-card-'.$course->id)
                ->click('@mobile-menu-button')
                ->waitFor('@mobile-sidebar-nav')
                ->assertPresent('@sidebar-course-'.$course->id.'-link-mobile')
                ->click('@sidebar-course-'.$course->id.'-link-mobile')
                ->waitForLocation('/courses/'.$course->id.'/classroom');
        });
    }

    public function test_active_item_highlight_is_applied_on_a_sub_route(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        // The `students` item is the Gestor-exclusive people-management
        // entry ( `users` is Admin-only now).
        $this->browse(function (Browser $browser) use ($gestor, $aluno): void {
            $browser->loginAs($gestor)
                ->visit(route('gestor.students.edit', $aluno))
                ->waitFor('@student-form');

            // The parent "Alunos" item must carry the `active` class on
            // its `gestor.students.edit` sub-route . Dusk's
            // `assertAttribute` does strict equality on the full attribute
            // string, so read the class and assert `active` membership
            // directly.
            $class = $browser->attribute('@sidebar-students-link', 'class');
            $this->assertNotNull($class, 'sidebar-students-link element is missing.');
            $this->assertStringContainsString(
                'active',
                $class,
                "Expected sidebar-students-link to carry the 'active' class on its gestor.students.edit sub-route ."
            );
        });
    }
}
