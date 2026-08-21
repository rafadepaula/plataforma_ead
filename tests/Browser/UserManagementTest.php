<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for user management screens.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada do Gestor sobre um usuário (criar → editar → inativar →
 * consequência no login) é um único método, e a jornada de matrícula
 * (matricular → revogar) é outro. Negativas independentes (cross-tenant,
 * rejeições de validação) seguem em métodos próprios.
 */
class UserManagementTest extends DuskTestCase
{
    public function test_gestor_user_management_full_lifecycle(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->browse(function (Browser $browser) use ($gestor): void {
            // 1. Criação
            $browser->loginAs($gestor)
                ->visit(route('users.create'))
                ->waitFor('@user-form')
                ->type('name', 'Aluno Dusk')
                ->type('email', 'aluno.dusk@example.com')
                ->type('cpf', '98765432100')
                ->select('role', 'aluno')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->press('Criar Usuário')
                ->waitForLocation('/users')
                ->assertSee('Usuário criado com sucesso.')
                ->assertSee('Aluno Dusk');

            $this->assertDatabaseHas('users', [
                'name' => 'Aluno Dusk',
                'email' => 'aluno.dusk@example.com',
                'cpf' => '98765432100',
                'org_id' => $gestor->org_id,
            ]);

            $aluno = User::where('email', 'aluno.dusk@example.com')->firstOrFail();

            // 2. Edição, a partir da listagem, na mesma sessão
            $browser->visit(route('users.index'))
                ->waitFor('@edit-user-'.$aluno->id)
                ->click('@edit-user-'.$aluno->id)
                ->waitFor('@user-form')
                ->clear('name')
                ->type('name', 'Aluno Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/users')
                ->assertSee('Usuário atualizado com sucesso.')
                ->assertSee('Aluno Editado');

            $this->assertDatabaseHas('users', [
                'id' => $aluno->id,
                'name' => 'Aluno Editado',
            ]);

            // 3. Inativação
            $browser->visit(route('users.edit', $aluno))
                ->waitFor('@user-form')
                ->select('@user-status-select', 'inactive')
                ->type('@user-status-reason', 'Aluno solicitou o encerramento do acesso.')
                ->press('Salvar Alterações')
                ->waitForText('Usuário atualizado com sucesso.')
                ->waitFor('@user-status-'.$aluno->id)
                // `<x-ui.badge>` carries `text-transform: uppercase`, and
                // Selenium's getText() returns the rendered (transformed)
                // texto: a caixa é decisão de tema, então a asserção ignora a caixa.
                ->assertTextEqualsIgnoringCase('@user-status-'.$aluno->id, 'Inativo');

            $this->assertDatabaseHas('users', [
                'id' => $aluno->id,
                'status' => 'inactive',
            ]);
            $this->assertDatabaseHas('audit_logs', [
                'event' => 'user.status_changed',
            ]);

            // 4. Consequência da inativação: o próprio usuário não loga mais.
            $browser->logout()
                ->visit('/login')
                ->type('@login-email', 'aluno.dusk@example.com')
                ->type('@login-password', 'password')
                ->press('@login-submit')
                ->waitForText('Essas credenciais não foram encontradas em nossos registros.')
                ->assertGuest();
        });

        $this->assertDatabaseHas('users', [
            'email' => 'aluno.dusk@example.com',
            'name' => 'Aluno Editado',
            'status' => 'inactive',
        ]);
    }

    public function test_gestor_course_enrollment_and_revocation_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Matriculável']);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $course): void {
            // 1. Matrícula manual
            $browser->loginAs($gestor)
                ->visit(route('courses.enrollments.index', $course))
                ->waitFor('@manual-enroll-form')
                ->type('user_id', (string) $aluno->id)
                ->press('Matricular')
                ->waitForText('Aluno matriculado com sucesso.')
                ->assertSee('Aluno Matriculável');

            $this->assertDatabaseHas('course_user', [
                'course_id' => $course->id,
                'user_id' => $aluno->id,
                'status' => 'active',
            ]);

            // 2. Revogação da matrícula recém-criada
            $browser->visit(route('courses.enrollments.index', $course))
                ->waitFor('@revoke-enrollment-'.$aluno->id)
                ->click('@revoke-enrollment-'.$aluno->id)
                ->waitForText('Matrícula revogada com sucesso.')
                ->assertSee('Aluno Matriculável')
                ->assertMissing('@revoke-enrollment-form-'.$aluno->id);
        });

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $aluno->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Rejeições de validação agrupadas na MESMA sessão de formulário — sem
     * recarregar navegador nem refazer login entre elas.
     */
    public function test_create_user_validation_rejections(): void
    {
        $gestor = User::factory()->gestor()->create();
        User::factory()->aluno()->create([
            'org_id' => $gestor->org_id,
            'email' => 'duplicado@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($gestor): void {
            // 1. E-mail duplicado
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
                ->waitForText('O valor informado para o campo e-mail já está em uso.')
                ->assertPathIs('/users/create')
                ->assertSee('O valor informado para o campo e-mail já está em uso.');

            $this->assertDatabaseCount('users', 2);

            // 2. Confirmação de senha divergente, mesma tela
            $browser->waitFor('@user-form')
                ->clear('name')
                ->type('name', 'Aluno Sem Confirmação')
                ->clear('email')
                ->type('email', 'sem.confirmacao@example.com')
                ->type('cpf', '98765432100')
                ->select('role', 'aluno')
                ->type('password', 'password')
                ->type('password_confirmation', 'outra-senha-qualquer')
                ->press('Criar Usuário')
                ->waitForText('A confirmação do campo senha não confere.')
                ->assertPathIs('/users/create');
        });

        $this->assertDatabaseMissing('users', ['email' => 'sem.confirmacao@example.com']);
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
