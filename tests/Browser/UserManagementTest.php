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
 * UC04 / RF04 — E2E coverage for user management screens.
 */
class UserManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_can_create_a_user_via_the_ui(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('users.create'))
                ->waitFor('@user-form')
                ->type('name', 'Aluno Dusk')
                ->type('email', 'aluno.dusk@example.com')
                ->type('cpf', '12345678901')
                ->select('role', 'aluno')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->press('Criar Usuário')
                ->waitForLocation('/users')
                ->assertSee('Usuário criado com sucesso.')
                ->assertSee('Aluno Dusk');
        });

        $this->assertDatabaseHas('users', [
            'name' => 'Aluno Dusk',
            'email' => 'aluno.dusk@example.com',
            'cpf' => '12345678901',
            'org_id' => $gestor->org_id,
        ]);
    }

    public function test_gestor_can_edit_a_user_via_the_ui(): void
    {
        $gestor = User::factory()->gestor()->create();
        $aluno = User::factory()->aluno()->create([
            'org_id' => $gestor->org_id,
            'name' => 'Aluno Original',
        ]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno): void {
            $browser->loginAs($gestor)
                ->visit(route('users.index'))
                ->waitFor('@edit-user-'.$aluno->id)
                ->click('@edit-user-'.$aluno->id)
                ->waitFor('@user-form')
                ->clear('name')
                ->type('name', 'Aluno Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/users')
                ->assertSee('Usuário atualizado com sucesso.')
                ->assertSee('Aluno Editado');
        });

        $this->assertDatabaseHas('users', [
            'id' => $aluno->id,
            'name' => 'Aluno Editado',
        ]);
    }

    /**
     * BLOQUEADO — funcionalidade ausente na UI.
     *
     * `UpdateUserRequest` aceita `status` (`sometimes|in:active,inactive`) e
     * `UserController::update()` registra o evento `user.status_changed`,
     * mas `resources/views/users/edit.blade.php` não expõe nenhum controle
     * de `status` (0 ocorrências de "status" em `resources/views/users/`).
     * Não há caminho de UI para inativar um usuário — apenas "Remover"
     * (soft delete via `users.destroy`).
     *
     * @see spec/coverage_review/missing_functionalities.md
     */
    public function test_gestor_can_deactivate_a_user_via_the_ui(): void
    {
        $this->markTestSkipped('UI ausente: sem controle de status em users/edit.blade.php.');
    }

    public function test_gestor_can_manually_enroll_a_student_in_a_course(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Matriculável']);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.enrollments.index', $course))
                ->waitFor('@manual-enroll-form')
                ->type('user_id', (string) $aluno->id)
                ->press('Matricular')
                ->waitForText('Aluno matriculado com sucesso.')
                ->assertSee('Aluno matriculado com sucesso.')
                ->assertSee('Aluno Matriculável');
        });

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $aluno->id,
            'status' => 'active',
        ]);
    }

    public function test_gestor_can_revoke_a_student_enrollment(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Matriculado']);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($aluno->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.enrollments.index', $course))
                ->waitFor('@revoke-enrollment-'.$aluno->id)
                ->click('@revoke-enrollment-'.$aluno->id)
                ->waitForText('Matrícula revogada com sucesso.')
                ->assertSee('Matrícula revogada com sucesso.')
                ->assertSee('Aluno Matriculado')
                ->assertMissing('@revoke-enrollment-form-'.$aluno->id);
        });

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $aluno->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_creating_a_user_with_a_duplicate_email_is_rejected(): void
    {
        $gestor = User::factory()->gestor()->create();
        User::factory()->aluno()->create([
            'org_id' => $gestor->org_id,
            'email' => 'duplicado@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('users.create'))
                ->waitFor('@user-form')
                ->type('name', 'Aluno Duplicado')
                ->type('email', 'duplicado@example.com')
                ->type('cpf', '98765432100')
                ->select('role', 'aluno')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->press('Criar Usuário')
                ->assertPathIs('/users/create')
                ->assertSee('The email has already been taken.');
        });

        $this->assertDatabaseCount('users', 2);
    }

    public function test_gestor_cannot_edit_a_user_from_another_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $gestorA = User::factory()->create(['org_id' => $orgA->id]);
        $gestorA->assignRole(RolesEnum::GESTOR->value);
        $alunoB = User::factory()->create(['org_id' => $orgB->id]);
        $alunoB->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($gestorA, $alunoB): void {
            $browser->loginAs($gestorA)
                ->visit(route('users.edit', $alunoB))
                ->assertSee('403');
        });
    }
}
