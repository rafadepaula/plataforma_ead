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
 * jornada do Gestor sobre um ALUNO matriculado (visualizar na listagem →
 * editar → inativar → consequência no login) é um único método, a jornada
 * de matrícula (matricular → revogar) é outra, e as rejeições de
 * validação da criação de usuários (agora exclusiva do Admin, via
 * "Entrar como") fecham o arquivo. Negativas independentes (cross-tenant,
 * permissões) seguem em métodos próprios ou nos testes Feature.
 */
class UserManagementTest extends DuskTestCase
{
    public function test_gestor_student_management_full_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->aluno()->create([
            'org_id' => $org->id,
            'name' => 'Aluno Dusk',
            'email' => 'aluno.dusk@example.com',
        ]);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno): void {
            // 1. Listagem: o Aluno matriculado aparece no diretório.
            $browser->loginAs($gestor)
                ->visit(route('gestor.students.index'))
                ->waitFor('@gestor-students-index')
                ->waitFor('@student-row-'.$aluno->id)
                ->assertSee('Aluno Dusk');

            // 2. Edição, a partir da listagem, na mesma sessão
            $browser->click('@edit-student-'.$aluno->id)
                ->waitFor('@student-form')
                ->clear('name')
                ->type('name', 'Aluno Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/gestor/students')
                ->waitFor('@gestor-students-index')
                ->assertSee('Aluno atualizado com sucesso.')
                ->assertSee('Aluno Editado');

            $this->assertDatabaseHas('users', [
                'id' => $aluno->id,
                'name' => 'Aluno Editado',
            ]);

            // 3. Inativação
            $browser->visit(route('gestor.students.edit', $aluno))
                ->waitFor('@student-form')
                ->select('@student-status-select', 'inactive')
                ->type('@student-status-reason', 'Aluno solicitou o encerramento do acesso.')
                ->press('Salvar Alterações')
                ->waitForText('Aluno atualizado com sucesso.')
                ->waitFor('@student-status-'.$aluno->id)
                // `<x-ui.badge>` carries `text-transform: uppercase`, and
                // Selenium's getText() returns the rendered (transformed)
                // texto: a caixa é decisão de tema, então a asserção ignora a caixa.
                ->assertTextEqualsIgnoringCase('@student-status-'.$aluno->id, 'Inativo');

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
            // 1. Matrícula manual via autocomplete (EnrollmentSearch.js):
            // digita parte do nome, espera o dropdown do endpoint JSON e
            // clica na opção do aluno — o `user_id` oculto é preenchido
            // pelo módulo antes do submit.
            $browser->loginAs($gestor)
                ->visit(route('courses.enrollments.index', $course))
                ->waitFor('@manual-enroll-form')
                ->type('@manual-enroll-search', 'Matriculável')
                ->waitFor('[data-enrollment-option="'.$aluno->id.'"]')
                ->click('[data-enrollment-option="'.$aluno->id.'"]')
                ->press('@manual-enroll-submit')
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
     * recarregar navegador nem refazer login entre elas. A criação de
     * usuários é exclusiva do Admin agora (`users.*` é `role:admin`), então
     * o Admin assume o contexto da Organização antes do formulário.
     */
    public function test_create_user_validation_rejections(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        User::factory()->aluno()->create([
            'org_id' => $org->id,
            'email' => 'duplicado@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            // "Entrar como" na Organização dá ao Admin o contexto de tenant
            // exigido pelo `users.*`.
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->visit(route('users.create'))
                ->waitFor('@user-form')
                // 1. E-mail duplicado
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
}
