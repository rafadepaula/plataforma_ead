<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * SPEC-00 §4/§5 — Spatie's `role:` middleware gate must correctly restrict
 * access per the 3 fundamental roles (`admin`, `gestor`, `aluno`).
 */
class RolesMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/__test/admin-only', fn () => 'ok')->middleware('role:admin');
            Route::get('/__test/gestor-only', fn () => 'ok')->middleware('role:gestor');
            Route::get('/__test/aluno-only', fn () => 'ok')->middleware('role:aluno');
        });
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/__test/admin-only')
            ->assertOk();
    }

    public function test_gestor_is_forbidden_from_admin_only_route(): void
    {
        $gestor = User::factory()->create();
        $gestor->assignRole('gestor');

        $this->actingAs($gestor)
            ->get('/__test/admin-only')
            ->assertForbidden();
    }

    public function test_gestor_can_access_gestor_only_route(): void
    {
        $gestor = User::factory()->create();
        $gestor->assignRole('gestor');

        $this->actingAs($gestor)
            ->get('/__test/gestor-only')
            ->assertOk();
    }

    public function test_aluno_is_forbidden_from_gestor_only_route(): void
    {
        $aluno = User::factory()->create();
        $aluno->assignRole('aluno');

        $this->actingAs($aluno)
            ->get('/__test/gestor-only')
            ->assertForbidden();
    }

    public function test_aluno_can_access_aluno_only_route(): void
    {
        $aluno = User::factory()->create();
        $aluno->assignRole('aluno');

        $this->actingAs($aluno)
            ->get('/__test/aluno-only')
            ->assertOk();
    }

    public function test_guest_is_redirected_away_from_a_role_gated_route(): void
    {
        $this->get('/__test/admin-only')->assertRedirect();
    }
}
