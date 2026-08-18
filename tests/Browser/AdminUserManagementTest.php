<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for the cross-org, Admin-only user management
 * screen (`admin.users.*`). Unlike `UserManagementTest` (the operational,
 * single-org `users.index`), this screen never needs an active
 * "Impersonate Org" context — logging in as a plain Admin (`org_id = null`,
 * no `session('active_org_id')` seeded) is enough, proving BUG-005 is
 * non-blocking here.
 */
class AdminUserManagementTest extends DuskTestCase
{
    public function test_admin_manages_users_across_orgs_without_impersonating(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $orgA = Organization::factory()->create(['name' => 'Organização Alfa']);
        $orgB = Organization::factory()->create(['name' => 'Organização Beta']);

        $alunoOrgA = User::factory()->create(['org_id' => $orgA->id, 'name' => 'Aluno Da Alfa', 'status' => 'active']);
        $alunoOrgA->assignRole(RolesEnum::ALUNO->value);

        $gestorOrgB = User::factory()->create(['org_id' => $orgB->id, 'name' => 'Gestor Da Beta', 'status' => 'active']);
        $gestorOrgB->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($admin, $orgA, $alunoOrgA, $gestorOrgB): void {
            // 1. Login as a plain Admin, no impersonation, straight to the
            //    cross-org screen. Users from BOTH orgs are visible at once.
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-users-index')
                ->assertPresent('@admin-user-row-'.$alunoOrgA->id)
                ->assertPresent('@admin-user-row-'.$gestorOrgB->id)
                ->assertSee('Aluno Da Alfa')
                ->assertSee('Gestor Da Beta');

            // 2. Filters narrow the listing to a single org.
            $browser->waitFor('@admin-users-filter-form')
                ->select('org_id', (string) $orgA->id)
                ->press('Filtrar')
                ->waitForLocation('/admin/users')
                ->assertSee('Aluno Da Alfa')
                ->assertDontSee('Gestor Da Beta');

            // 3. Reset the filter to see both rows again.
            $browser->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$alunoOrgA->id);

            // 4. Deactivate the aluno via the confirm modal.
            $browser->click('@toggle-status-admin-user-'.$alunoOrgA->id)
                ->waitForModalShown('confirm-status-'.$alunoOrgA->id)
                ->click('@confirm-modal-confirm-status-'.$alunoOrgA->id.'-confirm')
                ->waitForLocation('/admin/users')
                ->waitFor('@admin-user-status-'.$alunoOrgA->id)
                // `<x-ui.badge>` carries `text-transform: uppercase`, and
                // Selenium's getText() returns the rendered (transformed)
                // text — mirrors the assertion convention already used by
                // `UserManagementTest::test_gestor_user_management_full_lifecycle()`.
                ->assertSeeIn('@admin-user-status-'.$alunoOrgA->id, 'INATIVO');
        });

        $this->assertDatabaseHas('users', [
            'id' => $alunoOrgA->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.status_changed',
        ]);
    }

    public function test_admin_users_listing_shows_the_role_badge_for_all_three_roles(): void
    {
        $admin = User::factory()->create(['org_id' => null, 'name' => 'Administrador Root']);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Da Org']);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Da Org']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $gestor, $aluno): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$admin->id)
                ->assertAttribute('@admin-user-role-'.$admin->id, 'data-role', RolesEnum::ADMIN->value)
                ->assertAttribute('@admin-user-role-'.$gestor->id, 'data-role', RolesEnum::GESTOR->value)
                ->assertAttribute('@admin-user-role-'.$aluno->id, 'data-role', RolesEnum::ALUNO->value)
                // `<x-ui.badge>` carries `text-transform: uppercase`, and
                // Selenium's getText() returns the rendered (transformed)
                // text — mirrors the status-badge assertion convention above.
                ->assertSeeIn('@admin-user-role-'.$admin->id, mb_strtoupper(RolesEnum::label(RolesEnum::ADMIN->value)))
                ->assertSeeIn('@admin-user-role-'.$gestor->id, mb_strtoupper(RolesEnum::label(RolesEnum::GESTOR->value)))
                ->assertSeeIn('@admin-user-role-'.$aluno->id, mb_strtoupper(RolesEnum::label(RolesEnum::ALUNO->value)));
        });
    }

    /**
     * Full deletion lifecycle through the real UI: both `ON DELETE RESTRICT`
     * fail paths first (`certificates.user_id` and
     * `invitation_links.created_by`, guarded in `UserAdminController@destroy`
     * and rendered as a flash `error` by `bootstrap/app.php`), then the happy
     * path. Covers in the browser what
     * `UserAdminManagementTest::test_admin_cannot_delete_a_user_who_*` only
     * covers at the HTTP level — the Admin must see the error alert and find
     * the row still listed, never a raw 500.
     */
    public function test_admin_user_deletion_lifecycle_blocks_fk_conflicts_then_deletes(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $withCertificate = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Com Certificado']);
        $withCertificate->assignRole(RolesEnum::ALUNO->value);
        Certificate::factory()->create(['user_id' => $withCertificate->id, 'course_id' => $course->id]);

        $withInvitationLink = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Com Convite']);
        $withInvitationLink->assignRole(RolesEnum::GESTOR->value);
        InvitationLink::factory()->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $withInvitationLink->id,
        ]);

        $target = User::factory()->create(['org_id' => $org->id, 'name' => 'Usuário A Remover']);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $withCertificate, $withInvitationLink, $target): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$withCertificate->id);

            // 1. Deleting a user with issued certificates is refused with an
            //    error alert; the row survives.
            $browser->click('@delete-admin-user-'.$withCertificate->id)
                ->waitForModalShown('confirm-delete-'.$withCertificate->id)
                ->click('@confirm-modal-confirm-delete-'.$withCertificate->id.'-confirm')
                ->waitForText('Não é possível excluir um usuário com certificados emitidos.')
                ->assertPresent('@admin-user-row-'.$withCertificate->id)
                ->assertSee('Aluno Com Certificado');

            // 2. Same for a user who created invitation links.
            $browser->click('@delete-admin-user-'.$withInvitationLink->id)
                ->waitForModalShown('confirm-delete-'.$withInvitationLink->id)
                ->click('@confirm-modal-confirm-delete-'.$withInvitationLink->id.'-confirm')
                ->waitForText('Não é possível excluir um usuário que criou links de convite.')
                ->assertPresent('@admin-user-row-'.$withInvitationLink->id)
                ->assertSee('Gestor Com Convite');

            // 3. A user with no restricting rows is actually deleted.
            $browser->click('@delete-admin-user-'.$target->id)
                ->waitForModalShown('confirm-delete-'.$target->id)
                ->click('@confirm-modal-confirm-delete-'.$target->id.'-confirm')
                ->waitForLocation('/admin/users')
                ->assertDontSee('Usuário A Remover');
        });

        $this->assertDatabaseHas('users', ['id' => $withCertificate->id]);
        $this->assertDatabaseHas('users', ['id' => $withInvitationLink->id]);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_activates_an_inactive_user_via_confirm_modal(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $target = User::factory()->create(['org_id' => $org->id, 'name' => 'Usuário Inativo', 'status' => 'inactive']);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $target): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$target->id)
                ->click('@toggle-status-admin-user-'.$target->id)
                ->waitForModalShown('confirm-status-'.$target->id)
                ->click('@confirm-modal-confirm-status-'.$target->id.'-confirm')
                ->waitForLocation('/admin/users')
                ->waitFor('@admin-user-status-'.$target->id)
                ->assertSeeIn('@admin-user-status-'.$target->id, 'ATIVO');
        });

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_views_full_profile_and_edits_it_via_the_real_form(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $originOrg = Organization::factory()->create(['name' => 'Organização Origem']);
        $destinationOrg = Organization::factory()->create(['name' => 'Organização Destino']);
        $target = User::factory()->create(['org_id' => $originOrg->id, 'name' => 'Perfil Para Editar', 'status' => 'active']);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $target, $destinationOrg): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$target->id)
                ->click('@view-admin-user-'.$target->id)
                ->waitFor('@admin-user-show')
                ->assertSeeIn('@admin-user-show-name', 'Perfil Para Editar')
                ->click('@edit-admin-user')
                ->waitFor('@admin-user-form')
                ->clear('name')
                ->type('name', 'Perfil Editado')
                ->select('org_id', (string) $destinationOrg->id)
                ->select('role', 'gestor')
                ->press('Salvar Alterações')
                ->waitForLocation('/admin/users');
        });

        $target->refresh();
        $this->assertSame('Perfil Editado', $target->name);
        $this->assertSame($destinationOrg->id, $target->org_id);
        $this->assertTrue($target->hasRole(RolesEnum::GESTOR->value));
    }

    public function test_admin_edit_form_shows_validation_errors_on_invalid_submission(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $org = Organization::factory()->create();
        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($admin, $target): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.edit', $target))
                ->waitFor('@admin-user-form')
                ->type('cpf', '123')
                ->press('Salvar Alterações')
                ->waitForLocation('/admin/users/'.$target->id.'/edit')
                ->assertPresent('@error-cpf');
        });
    }

    public function test_admin_cannot_deactivate_their_own_account_via_the_status_toggle_modal(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@admin-user-row-'.$admin->id)
                ->click('@toggle-status-admin-user-'.$admin->id)
                ->waitForModalShown('confirm-status-'.$admin->id)
                ->click('@confirm-modal-confirm-status-'.$admin->id.'-confirm')
                ->waitForText('403');
        });

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'status' => 'active',
        ]);
    }

    public function test_gestor_and_aluno_are_blocked_from_direct_admin_users_urls(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $target = User::factory()->create(['org_id' => $org->id]);
        $target->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $target): void {
            $browser->loginAs($gestor)
                ->visit(route('admin.users.index'))
                ->assertSee('403');

            $browser->logout()
                ->loginAs($aluno)
                ->visit(route('admin.users.show', $target))
                ->assertSee('403');
        });
    }

    public function test_gestor_and_aluno_never_see_the_admin_users_nav_item(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $admin): void {
            $browser->loginAs($gestor)
                ->visit(route('users.index'))
                ->waitFor('.sidebar')
                ->assertMissing('@sidebar-admin-users-link');

            $browser->logout()
                ->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('.sidebar')
                ->assertMissing('@sidebar-admin-users-link');

            $browser->logout()
                ->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@sidebar-admin-users-link')
                ->assertPresent('@sidebar-admin-users-link');
        });
    }
}
