<?php

namespace Tests\Feature\Admin;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * cross-org, all-roles Admin user-management screen
 * (`admin.users.*`). This bucket (A) only covers what does not depend on
 * the Blade views owned by Bucket C: middleware/Policy authorization
 * boundaries, and the `update`/`status`/`destroy` actions, which redirect
 * rather than render a view. The `index`/`show`/`edit` success paths
 * (asserting rendered HTML) land once the `admin.users.*` views exist.
 */
class UserAdminManagementTest extends TestCase
{
    public function test_gestor_gets_403_on_every_admin_users_route(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($gestor);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.show', $target))->assertForbidden();
        $this->get(route('admin.users.edit', $target))->assertForbidden();
        $this->put(route('admin.users.update', $target), $this->validPayload($target))->assertForbidden();
        $this->patch(route('admin.users.status', $target), ['status' => 'inactive'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
    }

    public function test_aluno_gets_403_on_every_admin_users_route(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($aluno);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.show', $target))->assertForbidden();
        $this->get(route('admin.users.edit', $target))->assertForbidden();
        $this->put(route('admin.users.update', $target), $this->validPayload($target))->assertForbidden();
        $this->patch(route('admin.users.status', $target), ['status' => 'inactive'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_on_admin_users_index(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // Cross-org listing, all 3 roles, without any impersonation context
    // ------------------------------------------------------------------

    public function test_admin_sees_users_from_multiple_orgs_and_all_three_roles_in_one_page(): void
    {
        $this->actingAsAdmin();

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $gestorA = User::factory()->inOrg($orgA->id)->create(['name' => 'Gestor Da Org A']);
        $gestorA->assignRole(RolesEnum::GESTOR->value);

        $alunoB = User::factory()->inOrg($orgB->id)->create(['name' => 'Aluno Da Org B']);
        $alunoB->assignRole(RolesEnum::ALUNO->value);

        $otherAdmin = User::factory()->inOrg(null)->create(['name' => 'Outro Admin Do Sistema']);
        $otherAdmin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->get(route('admin.users.index'));

        $response->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertSee('Gestor Da Org A')
            ->assertSee('Aluno Da Org B')
            ->assertSee('Outro Admin Do Sistema');
    }

    public function test_admin_reaches_the_screen_without_any_active_impersonate_org_context(): void
    {
        // No `session('active_org_id')` seeded — proves  is
        // non-blocking here, unlike the operational `users.index`.
        $this->actingAsAdmin();

        $this->get(route('admin.users.index'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Filters — individually and combined, always paginated
    // ------------------------------------------------------------------

    public function test_filter_by_name(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $match = User::factory()->inOrg($org->id)->create(['name' => 'Fulano da Silva']);
        $match->assignRole(RolesEnum::ALUNO->value);
        $miss = User::factory()->inOrg($org->id)->create(['name' => 'Beltrano Souza']);
        $miss->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', ['name' => 'Fulano']));

        $response->assertOk()->assertSee('Fulano da Silva')->assertDontSee('Beltrano Souza');
    }

    public function test_filter_by_email(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $match = User::factory()->inOrg($org->id)->create(['email' => 'alvo@example.com']);
        $match->assignRole(RolesEnum::ALUNO->value);
        $miss = User::factory()->inOrg($org->id)->create(['email' => 'outro@example.com']);
        $miss->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', ['email' => 'alvo@']));

        $response->assertOk()->assertSee('alvo@example.com')->assertDontSee('outro@example.com');
    }

    public function test_filter_by_org_id(): void
    {
        $this->actingAsAdmin();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $inOrgA = User::factory()->inOrg($orgA->id)->create(['name' => 'Usuário Da Org A']);
        $inOrgA->assignRole(RolesEnum::ALUNO->value);
        $inOrgB = User::factory()->inOrg($orgB->id)->create(['name' => 'Usuário Da Org B']);
        $inOrgB->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', ['org_id' => $orgA->id]));

        $response->assertOk()->assertSee('Usuário Da Org A')->assertDontSee('Usuário Da Org B');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $active = User::factory()->inOrg($org->id)->create(['name' => 'Usuário Ativo']);
        $active->assignRole(RolesEnum::ALUNO->value);
        $inactive = User::factory()->inOrg($org->id)->inactive()->create(['name' => 'Usuário Inativo']);
        $inactive->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', ['status' => 'inactive']));

        $response->assertOk()->assertSee('Usuário Inativo')->assertDontSee('Usuário Ativo');
    }

    public function test_filter_by_role(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $gestor = User::factory()->inOrg($org->id)->create(['name' => 'É Gestor']);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->inOrg($org->id)->create(['name' => 'É Aluno']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', ['role' => RolesEnum::GESTOR->value]));

        $response->assertOk()->assertSee('É Gestor')->assertDontSee('É Aluno');
    }

    public function test_filter_by_created_at_range(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $old = User::factory()->inOrg($org->id)->create(['name' => 'Usuário Antigo', 'created_at' => now()->subYear()]);
        $old->assignRole(RolesEnum::ALUNO->value);
        $recent = User::factory()->inOrg($org->id)->create(['name' => 'Usuário Recente', 'created_at' => now()]);
        $recent->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.index', [
            'created_from' => now()->subDay()->toDateString(),
            'created_to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk()->assertSee('Usuário Recente')->assertDontSee('Usuário Antigo');
    }

    public function test_combined_filters_stay_paginated_with_query_string(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $aluno = User::factory()->inOrg($org)->create([
                'name' => "Aluno Filtrado {$i}",
            ]);
            $aluno->assignRole(RolesEnum::ALUNO->value);
        }

        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->get(route('admin.users.index', [
            'org_id' => $org->id,
            'status' => 'active',
            'role' => RolesEnum::ALUNO->value,
        ]));

        $response->assertOk();
        $response->assertSee('page=2', false);
    }

    // ------------------------------------------------------------------
    // Show — read-only full profile
    // ------------------------------------------------------------------

    public function test_admin_can_view_a_users_full_profile(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $user = User::factory()->inOrg($org->id)->create(['name' => 'Perfil Completo']);
        $user->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('admin.users.show', $user));

        $response->assertOk()
            ->assertViewIs('admin.users.show')
            ->assertSee('Perfil Completo');
    }

    public function test_admin_can_view_a_users_full_profile_with_enrollments_and_certificates(): void
    {
        $org = Organization::factory()->create();

        $user = User::factory()->inOrg($org->id)->create(['name' => 'Perfil Com Historico']);
        $user->assignRole(RolesEnum::ALUNO->value);

        $completedCourse = Course::factory()->for($org)->create(['title' => 'Curso Concluido']);
        $user->courses()->attach($completedCourse->id, [
            'status' => 'completed',
            'enrolled_at' => now()->subMonth(),
            'completed_at' => now(),
        ]);

        $cancelledCourse = Course::factory()->for($org)->create(['title' => 'Curso Cancelado']);
        $user->courses()->attach($cancelledCourse->id, [
            'status' => 'cancelled',
            'enrolled_at' => now()->subMonth(),
        ]);

        $issuedCertificate = Certificate::factory()
            ->for($user)
            ->for($completedCourse)
            ->create(['issued_at' => now()]);

        $revokedCertificate = Certificate::factory()
            ->revoked()
            ->for($user)
            ->for($cancelledCourse)
            ->create(['issued_at' => now()->subWeek()]);

        $this->actingAsAdmin();

        $response = $this->get(route('admin.users.show', $user));

        $response->assertOk()
            ->assertViewIs('admin.users.show')
            ->assertSee('Perfil Com Historico')
            ->assertSee('Curso Concluido')
            ->assertSee('Concluído')
            ->assertSee('Curso Cancelado')
            ->assertSee('Cancelado')
            ->assertSee('Emitido')
            ->assertSee('Revogado');

        $this->assertFalse($issuedCertificate->isRevoked());
        $this->assertTrue($revokedCertificate->isRevoked());
    }

    public function test_admin_can_view_the_edit_form(): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $user = User::factory()->inOrg($org->id)->create();
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertViewIs('admin.users.edit');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allRolesProvider(): iterable
    {
        yield 'admin' => [RolesEnum::ADMIN->value];
        yield 'gestor' => [RolesEnum::GESTOR->value];
        yield 'aluno' => [RolesEnum::ALUNO->value];
    }

    #[DataProvider('allRolesProvider')]
    public function test_admin_can_set_a_users_role_to_any_of_the_three_roles(string $role): void
    {
        $this->actingAsAdmin();
        $org = Organization::factory()->create();

        $user = User::factory()->inOrg($org->id)->create();
        $user->assignRole(RolesEnum::ALUNO->value);

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ];

        // An Admin has no `org_id` — moving a user INTO the admin role
        // must be allowed to clear it; every other role requires one.
        if ($role !== RolesEnum::ADMIN->value) {
            $payload['org_id'] = $org->id;
        }

        $response = $this->put(route('admin.users.update', $user), $payload);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($user->fresh()->hasRole($role));
    }

    public function test_admin_can_update_a_users_full_profile_across_orgs_including_role_and_org(): void
    {
        $admin = $this->actingAsAdmin();

        $originOrg = Organization::factory()->create();
        $destinationOrg = Organization::factory()->create();
        $target = User::factory()->inOrg($originOrg->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => 'Perfil Atualizado',
            'email' => $target->email,
            'role' => RolesEnum::GESTOR->value,
            'org_id' => $destinationOrg->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Perfil Atualizado', $target->name);
        $this->assertTrue($target->hasRole(RolesEnum::GESTOR->value));
        // o painel global não move membership: a conta continua na org de origem
        $this->assertNotNull($target->credentialFor($originOrg));
        $this->assertNull($target->credentialFor($destinationOrg));
    }

    public function test_admin_can_set_a_new_password_for_a_user(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);
        $originalHash = $target->credentialFor($org)->password;

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RolesEnum::ALUNO->value,
            'org_id' => $org->id,
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $freshCredential = $target->fresh()->credentialFor($org);
        $this->assertNotSame($originalHash, $freshCredential->password);
        $this->assertTrue(Hash::check('nova-senha-123', $freshCredential->password));
    }

    public function test_admin_can_promote_a_user_to_admin(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RolesEnum::ADMIN->value,
            'org_id' => '',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertTrue($target->hasRole(RolesEnum::ADMIN->value));
        // a conta por org não é removida pela promoção
        $this->assertNotNull($target->credentialFor($org));
    }

    public function test_admin_promotion_keeps_the_membership_even_when_the_org_field_is_sent(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::GESTOR->value);

        // Simulates the caller leaving the pre-filled Organização select
        // untouched while only changing the role to Administrador.
        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RolesEnum::ADMIN->value,
            'org_id' => (string) $org->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertTrue($target->hasRole(RolesEnum::ADMIN->value));
        // a conta por org não é removida pela promoção
        $this->assertNotNull($target->credentialFor($org));
    }

    public function test_org_id_sent_by_the_payload_is_ignored_by_the_global_surface(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RolesEnum::GESTOR->value,
            'org_id' => '',
        ]);

        // membership não é editável no painel global: org_id é ignorado
        $response->assertRedirect(route('admin.users.index'));
        $this->assertNotNull($target->fresh()->credentialFor($org));
    }

    public function test_admin_can_deactivate_an_active_user_and_it_is_audited(): void
    {
        $admin = $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $response = $this->patch(route('admin.users.status', $target), [
            'status' => 'inactive',
            'reason' => 'Violação de política',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('inactive', $target->fresh()->credentials()->first()->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.status_changed',
            'user_id' => $admin->id,
        ]);

        $log = AuditLog::where('event', 'user.status_changed')->latest('id')->first();
        $this->assertSame($target->id, $log->new_values['user_id']);
        $this->assertSame('active', $log->new_values['old_status']);
        $this->assertSame('inactive', $log->new_values['new_status']);
    }

    public function test_admin_can_activate_an_inactive_user(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->inactive()->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->patch(route('admin.users.status', $target), ['status' => 'active'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('active', $target->fresh()->credentials()->first()->status);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->patch(route('admin.users.status', $admin), ['status' => 'inactive']);

        $response->assertForbidden();
        $this->assertSame('active', $admin->fresh()->credentials()->first()->status);
    }

    public function test_admin_can_delete_another_users_account_and_it_is_audited(): void
    {
        $admin = $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);

        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.status_changed',
            'user_id' => $admin->id,
        ]);

        $log = AuditLog::where('event', 'user.status_changed')
            ->whereJsonContains('new_values->new_status', 'deleted')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($target->id, $log->new_values['user_id']);
    }

    public function test_admin_own_account_keeps_active_status_when_updated_via_the_full_update_form(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => RolesEnum::ADMIN->value,
            'status' => 'inactive',
        ]);

        // o formulário completo não carrega mais status: desativação é só via
        // PATCH, que bloqueia auto-desativação (test_admin_cannot_deactivate_their_own_account)
        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('active', $admin->fresh()->credentials()->first()->status);
    }

    public function test_admin_cannot_change_their_own_role_away_from_admin(): void
    {
        $admin = $this->actingAsAdmin();

        $org = Organization::factory()->create();

        $response = $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => RolesEnum::GESTOR->value,
            'org_id' => $org->id,
        ]);

        $response->assertForbidden();
        $this->assertTrue($admin->fresh()->hasRole(RolesEnum::ADMIN->value));
    }

    public function test_admin_cannot_delete_a_user_who_has_issued_certificates(): void
    {
        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->inOrg($org->id)->create();
        Certificate::factory()->create(['user_id' => $target->id, 'course_id' => $course->id]);

        $this->actingAsAdmin();

        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_a_user_who_has_created_invitation_links(): void
    {
        $org = Organization::factory()->create();
        $target = User::factory()->inOrg($org->id)->create();
        $target->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();
        InvitationLink::factory()->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $target->id,
        ]);

        $this->actingAsAdmin();

        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_a_user_who_created_invitation_links_in_another_org_while_impersonating_a_different_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $target = User::factory()->inOrg($orgA->id)->create();
        $target->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($orgA->id)->create();
        InvitationLink::factory()->create([
            'org_id' => $orgA->id,
            'course_id' => $course->id,
            'created_by' => $target->id,
        ]);

        $this->actingAsAdmin();
        session(['active_org_id' => $orgB->id]);

        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->delete(route('admin.users.destroy', $admin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /**
     * Regression guard: the operational `users.index` screen is
     * Admin-exclusive now (`role:admin` — see `routes/web.php`), so a
     * Gestor is blocked by middleware no matter which Organization they
     * target. The Gestor's own people surface is the exclusive Aluno
     * directory (`gestor.students.*`), whose own-org scoping is covered
     * by `GestorStudentManagementTest`.
     */
    public function test_operational_users_index_is_admin_exclusive(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $ownAluno = User::factory()->inOrg($org->id)->create();
        $ownAluno->assignRole(RolesEnum::ALUNO->value);
        $otherOrgAluno = User::factory()->inOrg($otherOrg->id)->create();
        $otherOrgAluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($gestor)->get(route('users.index'));

        $response->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(User $target): array
    {
        return [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RolesEnum::ALUNO->value,
        ];
    }
}
