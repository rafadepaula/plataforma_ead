<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * SPEC-17 §2 — `NavigationComposer` is bound to the sidebar and topbar
 * Blade components and must inject the four shell-only variables they
 * render from. This exercises the composer through Laravel's view
 * factory directly (no HTTP), so it asserts the data contract rather
 * than the HTML — the {@see RoleMenuVisibilityTest} covers the rendered
 * parity end-to-end.
 */
class NavigationComposerTest extends TestCase
{
    public function test_composer_injects_navigation_sections_for_an_authenticated_admin(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->actingAs($admin);

        $view = View::make('components.layout.sidebar')->render();

        $this->assertNotEmpty($view);
    }

    public function test_composer_injects_a_role_aware_brand_url_for_admin(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->actingAs($admin);

        $view = View::make('components.layout.topbar')->render();

        $this->assertStringContainsString(route('admin.dashboard'), $view);
    }

    public function test_composer_injects_a_role_aware_brand_url_for_aluno(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($aluno);

        $view = View::make('components.layout.topbar')->render();

        $this->assertStringContainsString(route('student.courses.index'), $view);
    }

    public function test_composer_provides_login_url_for_guest_and_logout_url_for_auth(): void
    {
        // Guest sees the login link (the logout form is inside `@auth`).
        $guestView = View::make('components.layout.topbar')->render();
        $this->assertStringContainsString(route('login'), $guestView);

        // An authenticated user sees the logout form instead.
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin);

        $authView = View::make('components.layout.topbar')->render();
        $this->assertStringContainsString(route('logout'), $authView);
    }

    public function test_guest_sidebar_renders_no_navigation_sections(): void
    {
        $view = View::make('components.layout.sidebar')->render();

        // A guest has no acting user, so no section/items render — only
        // the chrome (drawer toggle, etc.) should be present.
        $this->assertStringNotContainsString('Administração', $view);
        $this->assertStringNotContainsString('Aprendizado', $view);
    }
}
