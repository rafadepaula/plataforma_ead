<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * the rendered sidebar/topbar HTML must NEVER leak
 * a link to a route the acting user cannot access. These tests log in
 * as each role and load a real authenticated page, then assert the
 * presence/absence of restricted URLs in the served HTML — covering
 * the parity between the menu and the route's own `role:` middleware.
 *
 * The page used for each role is the one its own middleware guarantees:
 * `admin.dashboard` for Admin/Gestor, `student.courses.index` for Aluno.
 */
class RoleMenuVisibilityTest extends TestCase
{
    public function test_admin_menu_renders_all_admin_links_and_no_dead_hash(): void
    {
        //  `users.index` is an operational, single-org screen, so
        // the Admin only gets that link while impersonating an
        // Organization; see the dedicated cases below.
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('organizations.index'), false);
        $response->assertSee(route('users.index'), false);
        $response->assertSee(route('courses.index'), false);
        $response->assertSee(route('quiz-attempts.pending'), false);
        $response->assertSee(route('forum-moderation.index'), false);
        $response->assertSee(route('admin.audit-logs.index'), false);
        $response->assertSee(route('settings.edit'), false);
        //  no dead `href="#"` for any navigation item.
        $this->assertStringNotContainsString('sidebar-item" href="#"', $response->getContent());
    }

    public function test_gestor_menu_never_leaks_the_organizations_link(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('admin.dashboard'));

        $response->assertOk();
        //  `Organizações`, `Alunos & Usuários` (`users.index`),
        // `Auditoria` and `Configurações` are admin-exclusive.
        $response->assertDontSee(route('organizations.index'), false);
        $response->assertDontSeeText('Organizações');
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('admin.audit-logs.index'), false);
        $response->assertDontSee(route('settings.edit'), false);
        $response->assertDontSeeText('Configurações');
        //  "Meus Cursos" is Aluno-only (`role:aluno` parity).
        $response->assertDontSee(route('student.courses.index'), false);
        // The Gestor's exclusive Aluno directory is offered instead.
        $response->assertSee(route('gestor.students.index'), false);
    }

    public function test_aluno_menu_has_no_administration_links_at_all(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        //  every admin link is absent from the rendered HTML.
        $response->assertDontSee(route('admin.dashboard'), false);
        $response->assertDontSee(route('organizations.index'), false);
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('courses.index'), false);
        $response->assertDontSee(route('quiz-attempts.pending'), false);
        $response->assertDontSee(route('forum-moderation.index'), false);
        $response->assertDontSee(route('admin.audit-logs.index'), false);
        $response->assertDontSee(route('settings.edit'), false);
        $response->assertDontSeeText('Administração');
    }

    /**
     *  the forum is scoped to ONE course, so the sidebar never offers
     * a GENERALIST "Fórum" entry (`sidebar-forum-link` stays structurally
     * dead); what it does offer now is the per-course forum link inside
     * each "Meus Cursos" block. The classroom card remains a parallel
     * entry point.
     */
    public function test_no_generalist_forum_item_exists_but_each_course_block_links_its_own_forum(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));
        $html = $response->getContent();

        $response->assertOk();
        // 1. O item generalista segue morto, em ambos os renders.
        $this->assertStringNotContainsString('dusk="sidebar-forum-link"', $html);
        $this->assertStringNotContainsString('dusk="sidebar-forum-link-mobile"', $html);
        // 2. O link POR CURSO é o novo caminho do bloco.
        $this->assertStringContainsString('dusk="sidebar-course-'.$course->id.'-forum"', $html);
        $this->assertStringContainsString('dusk="sidebar-course-'.$course->id.'-forum-mobile"', $html);
        $response->assertSee(route('forum.index', $course), false);
        // 3. O card da sala de aula segue de pé.
        $classroom = $this->actingAs($aluno)->get(route('classroom.show', $course));

        $classroom->assertOk();
        $classroom->assertSee(route('forum.index', $course), false);
        $classroom->assertSee('dusk="classroom-forum-card"', false);
    }

    /**
     *  the enrolled-course blocks render in BOTH renders (desktop
     * `<aside>` and mobile Offcanvas): active AND completed enrollments
     * (the certificate is born exactly at completion, so completed
     * blocks must render), each with the pivot progress bar, lesson
     * counts and forum link. `cancelled` never renders, and the
     * "Ver todos os cursos" child is persistent — with the parent
     * anchor gone it is the only menu path to `/meus-cursos`.
     */
    public function test_aluno_sees_course_blocks_with_progress_in_both_renders(): void
    {
        $org = Organization::factory()->create();
        $active = Course::factory()->for($org)->create(['title' => 'Curso Atalho']);
        $completed = Course::factory()->for($org)->create(['title' => 'A Concluído']);
        $cancelled = Course::factory()->for($org)->create(['title' => 'B Cancelado']);
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($active->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 42]);
        $aluno->courses()->attach($completed->id, ['status' => 'completed', 'enrolled_at' => now()]);
        $aluno->courses()->attach($cancelled->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));
        $html = $response->getContent();

        $response->assertOk();
        foreach ([$active, $completed] as $course) {
            $this->assertStringContainsString('dusk="sidebar-course-'.$course->id.'-link"', $html);
            $this->assertStringContainsString('dusk="sidebar-course-'.$course->id.'-link-mobile"', $html);
        }
        $this->assertStringNotContainsString('dusk="sidebar-course-'.$cancelled->id.'-link"', $html);
        $response->assertSee(route('classroom.show', $active), false);
        //  progress bar fed by `course_user.progress_percentage`.
        $this->assertStringContainsString('aria-valuenow="42"', $html);
        //  the persistent escape hatch, in both renders.
        $this->assertStringContainsString('dusk="sidebar-see-all-link"', $html);
        $this->assertStringContainsString('dusk="sidebar-see-all-link-mobile"', $html);
        $response->assertSee('Ver todos os cursos');
    }

    public function test_aluno_without_enrollments_gets_only_the_ver_todos_link(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('dusk="sidebar-course-', $response->getContent());
        $this->assertStringContainsString('dusk="sidebar-see-all-link"', $response->getContent());
        $response->assertSee('Ver todos os cursos');
    }

    /**
     *  the block's certificate line follows the issuance rule: a
     * button ONLY for a live (non-revoked) certificate — revoked and
     * never-issued render NOTHING — and the link is the download route
     * the owner can actually follow.
     */
    public function test_course_block_shows_a_certificate_button_only_for_a_live_certificate(): void
    {
        $org = Organization::factory()->create();
        $live = Course::factory()->for($org)->create(['title' => 'A Live']);
        $revoked = Course::factory()->for($org)->create(['title' => 'B Revogado']);
        $none = Course::factory()->for($org)->create(['title' => 'C Nunca Emitido']);
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        foreach ([$live, $revoked, $none] as $course) {
            $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        }

        $liveCertificate = Certificate::factory()->create(['user_id' => $aluno->id, 'course_id' => $live->id]);
        Certificate::factory()->revoked()->create(['user_id' => $aluno->id, 'course_id' => $revoked->id]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('dusk="sidebar-course-'.$live->id.'-certificate"', $html);
        $this->assertStringContainsString('dusk="sidebar-course-'.$live->id.'-certificate-mobile"', $html);
        $this->assertStringContainsString(route('certificates.download', ['certificate' => $liveCertificate->id]), $html);
        $this->assertStringNotContainsString('dusk="sidebar-course-'.$revoked->id.'-certificate"', $html);
        $this->assertStringNotContainsString('dusk="sidebar-course-'.$none->id.'-certificate"', $html);

        //  the offered link is real: the owner follows it and gets
        // the PDF stream. (Downloading a REVOKED certificate is the
        // download endpoint's own contract — the menu's contract is
        // simply to never offer that line.)
        $this->actingAs($aluno)
            ->get(route('certificates.download', ['certificate' => $liveCertificate->id]))
            ->assertOk();
    }

    public function test_active_item_highlight_is_applied_on_a_sub_route(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        // `users.create` is a sub-route matched by the `users.*` active
        // pattern  — the parent item must carry the `active` class.
        // The Admin needs an impersonated Organization for the item to be
        // visible at all .
        $response = $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('users.create'));

        $response->assertOk();
        $this->assertStringContainsString('sidebar-users-link', $response->getContent());
    }

    /**
     * `users.index` resolves its tenant strictly
     * (`ResolvesOrgContext`), so a system Admin with neither an own
     * `org_id` nor an impersonated Organization cannot reach it. The menu
     * must therefore not offer the item — in NEITHER render: the desktop
     * `<aside>` (`dusk="sidebar-users-link"`) nor the mobile Offcanvas
     * (`dusk="sidebar-users-link-mobile"`).
     */
    public function test_admin_without_an_active_org_context_never_sees_the_users_link(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertStringNotContainsString('dusk="sidebar-users-link"', $response->getContent());
        $this->assertStringNotContainsString('dusk="sidebar-users-link-mobile"', $response->getContent());
        $response->assertDontSeeText('Alunos & Usuários');
        // The rest of the system-administration block is untouched.
        //  `courses.index` is NOT part of it anymore: it is an
        // Organization-scoped item, covered by the cases below.
        $response->assertSee(route('organizations.index'), false);
        $response->assertSee(route('admin.audit-logs.index'), false);
        $response->assertSee(route('settings.edit'), false);
    }

    // ──  Admin menu scope & the "Impersonate" section ────────

    /**
     *  in global context the Admin's rendered menu must not offer
     * any Organization-scoped item, in EITHER render (desktop `<aside>`
     * and mobile Offcanvas), and must not emit an empty "Impersonate"
     * heading.
     */
    public function test_admin_without_impersonation_sees_no_organization_scoped_items(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $html = $response->getContent();

        $response->assertOk();
        foreach (['courses', 'quiz-attempts', 'forum-moderation'] as $key) {
            $this->assertStringNotContainsString('dusk="sidebar-'.$key.'-link"', $html);
            $this->assertStringNotContainsString('dusk="sidebar-'.$key.'-link-mobile"', $html);
        }
        $response->assertDontSeeText('Impersonate');
        $response->assertDontSeeText('Cursos e Módulos');
        $response->assertDontSeeText('Redações Pendentes');
        $response->assertDontSeeText('Moderação do Fórum');
    }

    /**
     *  with an active Impersonate Org the three operational items
     * come back, under their own "Impersonate" heading, in both renders.
     */
    public function test_admin_impersonating_an_org_sees_the_impersonate_section_in_both_renders(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('admin.dashboard'));
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSeeText('Impersonate');
        foreach (['courses', 'quiz-attempts', 'forum-moderation'] as $key) {
            $this->assertStringContainsString('dusk="sidebar-'.$key.'-link"', $html);
            $this->assertStringContainsString('dusk="sidebar-'.$key.'-link-mobile"', $html);
        }
        //  the newly grouped links are real, reachable URLs.
        $response->assertSee(route('courses.index'), false);
        $this->assertStringNotContainsString('sidebar-item" href="#"', $html);
    }

    /**
     *  "Meus Cursos" is gone from the Admin's menu: the section
     * heading (which replaced the old parent anchor) never renders for
     * them either.
     */
    public function test_admin_never_sees_the_meus_cursos_section(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        foreach ([[], ['active_org_id' => $org->id]] as $session) {
            $response = $this->actingAs($admin)
                ->withSession($session)
                ->get(route('admin.dashboard'));

            $response->assertOk();
            $response->assertDontSeeText('Meus Cursos');
            $response->assertDontSee('aulas concluídas', false);
            $this->assertStringNotContainsString('dusk="sidebar-course-', $response->getContent());
        }
    }

    /**
     *  the Gestor is staff, not a learner: "Meus Cursos" is
     * Aluno-only (mirroring the route's own `role:aluno`), so the
     * "Aprendizado" heading is never rendered for a Gestor either.
     */
    public function test_gestor_menu_is_untouched_and_never_shows_an_impersonate_section(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Impersonate');
        $response->assertSeeText('Administração');
        $response->assertSee(route('courses.index'), false);
        $response->assertSee(route('quiz-attempts.pending'), false);
        $response->assertSee(route('forum-moderation.index'), false);
        // The Gestor-exclusive Aluno directory replaces it.
        $response->assertSee(route('gestor.students.index'), false);
        //  "Meus Cursos" is gone from the Gestor's menu too:
        // the section (and its blocks) never render for staff.
        $response->assertDontSee(route('student.courses.index'), false);
        $response->assertDontSeeText('Meus Cursos');
        $response->assertDontSee('aulas concluídas', false);
        $this->assertStringNotContainsString('dusk="sidebar-course-', $response->getContent());
    }

    public function test_admin_impersonating_an_org_sees_the_users_link_in_both_renders(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('dusk="sidebar-users-link"', $response->getContent());
        $this->assertStringContainsString('dusk="sidebar-users-link-mobile"', $response->getContent());
        // And the link it offers actually works.
        $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('users.index'))
            ->assertOk();
    }

    /**
     *  `users.index` is Admin-exclusive now (`role:admin`), so the
     * Gestor gets the dedicated `students` item instead — in BOTH renders
     * (desktop `<aside>` and mobile Offcanvas) — and the admin screen's
     * URL must be blocked by its own middleware, not just absent from the
     * menu.
     */
    public function test_gestor_sees_the_students_link_in_both_renders_and_not_the_users_link(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('dusk="sidebar-students-link"', $response->getContent());
        $this->assertStringContainsString('dusk="sidebar-students-link-mobile"', $response->getContent());
        $this->assertStringNotContainsString('dusk="sidebar-users-link"', $response->getContent());
        $this->assertStringNotContainsString('dusk="sidebar-users-link-mobile"', $response->getContent());

        // The offered link actually works...
        $this->actingAs($gestor)->get(route('gestor.students.index'))->assertOk();
        // ...and the admin-exclusive screen it replaced 403s.
        $this->actingAs($gestor)->get(route('users.index'))->assertForbidden();
    }
}
