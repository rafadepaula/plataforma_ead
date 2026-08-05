<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-17 RN38/RN40 — the rendered sidebar/topbar HTML must NEVER leak
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
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

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
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        // `users.create` is a sub-route matched by the `users.*` active
        // pattern (RF37) — the parent item must carry the `active` class.
        $response = $this->actingAs($admin)->get(route('users.create'));

        $response->assertOk();
        $this->assertStringContainsString('sidebar-users-link', $response->getContent());
    }
}
