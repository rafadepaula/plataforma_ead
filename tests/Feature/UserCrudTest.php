<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * RF04 — Admin/Gestor CRUD of Alunos and Gestores, always scoped by
 * server-resolved `org_id` (never trusted from request input).
 */
class UserCrudTest extends TestCase
{
    public function test_gestor_can_create_an_aluno_in_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->post('/users', [
            'name' => 'Novo Aluno',
            'email' => 'aluno.novo@example.com',
            'role' => RolesEnum::ALUNO->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'aluno.novo@example.com')->firstOrFail();
        $this->assertSame($org->id, $user->org_id);
        $this->assertTrue($user->hasRole(RolesEnum::ALUNO->value));
    }

    public function test_gestor_creating_a_user_ignores_any_org_id_sent_in_the_request(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->post('/users', [
            'name' => 'Tentativa',
            'email' => 'tentativa@example.com',
            'role' => RolesEnum::ALUNO->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'org_id' => $otherOrg->id,
        ]);

        $user = User::where('email', 'tentativa@example.com')->firstOrFail();
        $this->assertSame($org->id, $user->org_id);
        $this->assertNotSame($otherOrg->id, $user->org_id);
    }

    public function test_gestor_cannot_see_or_edit_users_from_another_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $outsideUser = User::factory()->create(['org_id' => $otherOrg->id]);
        $outsideUser->assignRole(RolesEnum::ALUNO->value);

        $this->get("/users/{$outsideUser->id}/edit")->assertForbidden();
    }

    public function test_gestor_can_view_the_users_index_for_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->get('/users')
            ->assertOk()
            ->assertViewIs('users.index')
            ->assertSee($aluno->name);
    }

    public function test_admin_impersonating_an_org_can_view_that_orgs_users_index(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->get('/users')
            ->assertOk()
            ->assertSee($aluno->name);
    }

    public function test_gestor_can_view_the_edit_form_for_an_aluno_in_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->get("/users/{$aluno->id}/edit")
            ->assertOk()
            ->assertViewIs('users.edit');
    }

    public function test_admin_impersonating_an_org_can_manage_that_orgs_users(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

        $response = $this->post('/users', [
            'name' => 'Aluno do Admin',
            'email' => 'aluno.admin@example.com',
            'role' => RolesEnum::ALUNO->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'aluno.admin@example.com')->firstOrFail();
        $this->assertSame($org->id, $user->org_id);
    }

    public function test_admin_without_active_org_context_gets_redirected_back_with_error_on_create(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/users', [
            'name' => 'Sem Org',
            'email' => 'sem.org@example.com',
            'role' => RolesEnum::ALUNO->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse(User::where('email', 'sem.org@example.com')->exists());
    }

    public function test_aluno_is_forbidden_from_all_user_management_routes(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $colleague = User::factory()->create(['org_id' => $org->id]);
        $colleague->assignRole(RolesEnum::ALUNO->value);

        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get("/users/{$colleague->id}/edit")->assertForbidden();
        $this->put("/users/{$colleague->id}", ['name' => 'x', 'email' => 'x@example.com', 'role' => RolesEnum::ALUNO->value])->assertForbidden();
        $this->delete("/users/{$colleague->id}")->assertForbidden();
    }

    public function test_gestor_can_view_the_create_user_form(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get('/users/create')
            ->assertOk()
            ->assertViewIs('users.create');
    }

    public function test_admin_without_active_org_context_is_forbidden_from_updating_a_user(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        // No impersonation: `actingAsAdmin()` with no org sets no
        // `active_org_id`, so `sharesOrgContext()` must deny access even
        // though the acting user is an Admin.
        $this->actingAsAdmin();

        $this->get("/users/{$aluno->id}/edit")->assertForbidden();
        $this->put("/users/{$aluno->id}", ['name' => 'x', 'email' => 'x@example.com', 'role' => RolesEnum::ALUNO->value])->assertForbidden();
        $this->delete("/users/{$aluno->id}")->assertForbidden();
    }

    public function test_gestor_can_update_an_aluno_in_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id, 'email' => 'antigo@example.com']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->put("/users/{$aluno->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'novo.email@example.com',
            'role' => RolesEnum::ALUNO->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $aluno->refresh();
        $this->assertSame('Nome Atualizado', $aluno->name);
        $this->assertSame('novo.email@example.com', $aluno->email);
    }

    public function test_gestor_can_delete_an_aluno_in_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->delete("/users/{$aluno->id}")->assertRedirect(route('users.index'));

        $this->assertFalse(User::whereKey($aluno->id)->exists());
    }
}
