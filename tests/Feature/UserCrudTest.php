<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 *  User CRUD authorization matrix. The operational
 * `users.*` screen is Admin-exclusive (`role:admin`): an Admin manages a
 * single Organization's Alunos AND Gestores while impersonating it, a
 * Gestor is blocked by middleware first and gets the dedicated
 * `gestor.students.*` Aluno directory instead, and an Aluno has no
 * access at all. Every path is scoped by server-resolved `org_id`
 * (never trusted from request input).
 */
class UserCrudTest extends TestCase
{
    public function test_gestor_is_forbidden_from_creating_a_user_via_the_admin_screen(): void
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

        //  `users.*` is Admin-exclusive (`role:admin`): the
        // Gestor is blocked by middleware, before any Policy or
        // validation runs — new Alunos enter their Organization via
        // invitation links, the shared CSV import or per-Course manual
        // enrollment instead.
        $response->assertForbidden();
        $this->assertFalse(User::where('email', 'aluno.novo@example.com')->exists());
    }

    public function test_admin_impersonating_an_org_creating_a_user_ignores_any_org_id_sent_in_the_request(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsAdmin($org);

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

    public function test_gestor_can_view_their_own_orgs_enrolled_students_directory(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->for($org)->create();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->get('/gestor/students')
            ->assertOk()
            ->assertViewIs('gestor.students.index')
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

    public function test_gestor_is_forbidden_from_the_admin_users_screens(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        //  the whole operational stack (list, create form,
        // edit form, update, delete) is Admin-only now — blocked by
        // `role:admin` middleware regardless of the target's org or role.
        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get("/users/{$aluno->id}/edit")->assertForbidden();
        $this->put("/users/{$aluno->id}", ['name' => 'x', 'email' => 'x@example.com', 'role' => RolesEnum::ALUNO->value])->assertForbidden();
        $this->delete("/users/{$aluno->id}")->assertForbidden();
    }

    public function test_admin_without_active_org_context_is_redirected_back_from_the_users_index(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.dashboard'))->get('/users');

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('error');
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
        // The Gestor directory is staff-only too.
        $this->get('/gestor/students')->assertForbidden();
        $this->get("/gestor/students/{$colleague->id}/edit")->assertForbidden();
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

    public function test_creating_a_user_with_a_checksum_invalid_cpf_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

        $response = $this->post('/users', [
            'name' => 'Novo Aluno',
            'email' => 'aluno.cpf.invalido@example.com',
            'role' => RolesEnum::ALUNO->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cpf' => '111.444.777-36',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertFalse(User::where('email', 'aluno.cpf.invalido@example.com')->exists());
    }

    public function test_gestor_can_update_an_enrolled_aluno_of_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id, 'email' => 'antigo@example.com']);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->for($org)->create();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->put("/gestor/students/{$aluno->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'novo.email@example.com',
        ]);

        $response->assertRedirect(route('gestor.students.index'));
        $aluno->refresh();
        $this->assertSame('Nome Atualizado', $aluno->name);
        $this->assertSame('novo.email@example.com', $aluno->email);
    }

    public function test_gestor_can_update_an_enrolled_alunos_password(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id, 'password' => bcrypt('old-password')]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->for($org)->create();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $originalHash = $aluno->password;

        $response = $this->put("/gestor/students/{$aluno->id}", [
            'name' => $aluno->name,
            'email' => $aluno->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('gestor.students.index'));
        $aluno->refresh();
        $this->assertNotSame($originalHash, $aluno->password);
        $this->assertTrue(Hash::check('brand-new-password', $aluno->password));
    }

    public function test_updating_a_student_with_a_checksum_invalid_cpf_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id, 'cpf' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->for($org)->create();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->put("/gestor/students/{$aluno->id}", [
            'name' => $aluno->name,
            'email' => $aluno->email,
            'cpf' => '111.444.777-36',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertNull($aluno->fresh()->cpf);
    }

    public function test_gestor_can_delete_an_enrolled_aluno_of_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->for($org)->create();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->delete("/gestor/students/{$aluno->id}")->assertRedirect(route('gestor.students.index'));

        $this->assertFalse(User::whereKey($aluno->id)->exists());
    }
}
