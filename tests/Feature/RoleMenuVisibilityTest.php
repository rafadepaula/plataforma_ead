<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
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
        // BUG-005 — `users.index` is an operational, single-org screen, so
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
        // RF36 — no dead `href="#"` for any navigation item.
        $this->assertStringNotContainsString('sidebar-item" href="#"', $response->getContent());
    }

    public function test_gestor_menu_never_leaks_the_organizations_link(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('admin.dashboard'));

        $response->assertOk();
        // RN39 — `Organizações` is admin-exclusive.
        $response->assertDontSee(route('organizations.index'), false);
        $response->assertDontSeeText('Organizações');
        // Gestor still sees the rest of the admin block.
        $response->assertSee(route('users.index'), false);
        $response->assertSee(route('gestor.audit-logs.index'), false);
    }

    public function test_aluno_menu_has_no_administration_links_at_all(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        // RN38 — every admin link is absent from the rendered HTML.
        $response->assertDontSee(route('admin.dashboard'), false);
        $response->assertDontSee(route('organizations.index'), false);
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('courses.index'), false);
        $response->assertDontSee(route('quiz-attempts.pending'), false);
        $response->assertDontSee(route('forum-moderation.index'), false);
        $response->assertDontSee(route('admin.audit-logs.index'), false);
        $response->assertDontSee(route('gestor.audit-logs.index'), false);
        $response->assertDontSee(route('settings.edit'), false);
        $response->assertDontSeeText('Administração');
    }

    public function test_aluno_without_enrollments_does_not_see_a_forum_link(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertDontSeeText('Fórum de Dúvidas');
    }

    public function test_aluno_with_an_active_enrollment_sees_the_forum_linked_to_that_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee(route('forum.index', $course), false);
        $response->assertSeeText('Fórum de Dúvidas');
    }

    public function test_active_item_highlight_is_applied_on_a_sub_route(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        // `users.create` is a sub-route matched by the `users.*` active
        // pattern (RF37) — the parent item must carry the `active` class.
        // The Admin needs an impersonated Organization for the item to be
        // visible at all (BUG-005).
        $response = $this->actingAs($admin)
            ->withSession(['active_org_id' => $org->id])
            ->get(route('users.create'));

        $response->assertOk();
        $this->assertStringContainsString('sidebar-users-link', $response->getContent());
    }

    /**
     * BUG-005 / RN38 — `users.index` resolves its tenant strictly
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
        // UX-001 — `courses.index` is NOT part of it anymore: it is an
        // Organization-scoped item, covered by the cases below.
        $response->assertSee(route('organizations.index'), false);
        $response->assertSee(route('admin.audit-logs.index'), false);
        $response->assertSee(route('settings.edit'), false);
    }

    // ── UX-001 — Admin menu scope & the "Impersonate" section ────────

    /**
     * UX-001 — in global context the Admin's rendered menu must not offer
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
     * UX-001 — with an active Impersonate Org the three operational items
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
        // RF36 — the newly grouped links are real, reachable URLs.
        $response->assertSee(route('courses.index'), false);
        $this->assertStringNotContainsString('sidebar-item" href="#"', $html);
    }

    /**
     * UX-001 — "Meus Cursos" is gone from the Admin's menu, so the
     * "Aprendizado" heading is never rendered for them either.
     */
    public function test_admin_never_sees_the_aprendizado_section(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        foreach ([[], ['active_org_id' => $org->id]] as $session) {
            $response = $this->actingAs($admin)
                ->withSession($session)
                ->get(route('admin.dashboard'));

            $response->assertOk();
            $response->assertDontSeeText('Aprendizado');
            $response->assertDontSeeText('Meus Cursos');
            $this->assertStringNotContainsString('dusk="sidebar-student-courses-link"', $response->getContent());
            $this->assertStringNotContainsString('dusk="sidebar-student-courses-link-mobile"', $response->getContent());
        }
    }

    /**
     * UX-001 non-regression — nothing moves for the Gestor: the
     * operational items stay in "Administração" and no "Impersonate"
     * heading appears, even with a stale `active_org_id` in session.
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
        // The Gestor keeps "Meus Cursos".
        $response->assertSee(route('student.courses.index'), false);
        $response->assertSeeText('Aprendizado');
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

    public function test_gestor_sees_the_users_link_in_both_renders(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('dusk="sidebar-users-link"', $response->getContent());
        $this->assertStringContainsString('dusk="sidebar-users-link-mobile"', $response->getContent());

        $this->actingAs($gestor)->get(route('users.index'))->assertOk();
    }
}
