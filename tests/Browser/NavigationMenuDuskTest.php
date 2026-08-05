<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-17 §4 — E2E coverage of the dynamic navigation menu: each role
 * logs in, opens an authenticated page rendered inside the app shell
 * (which mounts `components.layout.sidebar`/`topbar`), and Dusk verifies
 * the expected sidebar links are present/absent in the live DOM plus the
 * active-highlight behaviour on a sub-route. Mirrors the Feature-level
 * `RoleMenuVisibilityTest` but drives a real browser.
 */
class NavigationMenuDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_sidebar_renders_every_administration_link(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertPresent('@sidebar-dashboard-link')
                ->assertPresent('@sidebar-organizations-link')
                ->assertPresent('@sidebar-users-link')
                ->assertPresent('@sidebar-courses-link')
                ->assertPresent('@sidebar-quiz-attempts-link')
                ->assertPresent('@sidebar-forum-moderation-link')
                ->assertPresent('@sidebar-audit-logs-link')
                ->assertPresent('@sidebar-settings-link');
        });
    }

    public function test_gestor_sidebar_hides_organizations_but_shows_the_rest(): void
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

    public function test_aluno_sidebar_has_no_administration_links(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($aluno): void {
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
        });
    }

    public function test_aluno_with_an_enrollment_sees_the_forum_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->browse(function (Browser $browser) use ($aluno, $course): void {
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@open-classroom-'.$course->id)
                ->assertPresent('@sidebar-forum-link');
        });
    }

    public function test_active_item_highlight_is_applied_on_a_sub_route(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
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
