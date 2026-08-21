<?php

namespace Tests\Browser\Auth;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the login screen ( §5 mandates
 * Dusk coverage de todas as telas). Isolamento via `DatabaseTruncation`
 * herdado de `Tests\DuskTestCase` (nunca `RefreshDatabase`, pois o Dusk
 * dirige navegador e app como processos/conexões HTTP separados).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de sessão (entrar → destino por papel → sair), as rejeições de
 * credencial na mesma tela de login, e a jornada de recuperação de senha
 * (solicitar → token inválido → token válido → entrar com a nova senha).
 */
class LoginTest extends DuskTestCase
{
    public function test_login_redirects_by_role_and_logout_lifecycle(): void
    {
        $aluno = User::factory()->create([
            'email' => 'aluno@example.com',
            'password' => bcrypt('correct-password'),
        ]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $gestor = User::factory()->gestor()->create([
            'email' => 'gestor@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $admin = User::factory()->create([
            'org_id' => null,
            'email' => 'admin@example.com',
            'password' => bcrypt('correct-password'),
        ]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($aluno, $gestor, $admin): void {
            // 1. Aluno entra pelo formulário e cai na sala de aula.
            $browser->visit('/login')
                ->assertPresent('@login-form')
                ->type('@login-email', 'aluno@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticatedAs($aluno);

            // 2. Gestor cai no dashboard administrativo.
            $browser->logout()
                ->visit('/login')
                ->type('@login-email', 'gestor@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForLocation('/admin/dashboard')
                ->assertAuthenticatedAs($gestor);

            // 3. Admin idem.
            $browser->logout()
                ->visit('/login')
                ->type('@login-email', 'admin@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForLocation('/admin/dashboard')
                ->assertAuthenticatedAs($admin);

            // 4. Sair pelo controle do topbar encerra a sessão de verdade.
            //    (O controle vive no app shell — `layouts.app` —, por isso a
            //    saída acontece a partir de uma tela administrativa real.)
            $browser->visit('/organizations')
                ->waitFor('form[action$="/logout"] button')
                ->click('form[action$="/logout"] button')
                ->waitForLocation('/')
                ->assertGuest();
        });
    }

    /**
     * Rejeições de credencial exercitadas em sequência na MESMA tela de
     * login: senha errada e usuário inativo (RN — `status=active` guarda o
     * login) devolvem a mesma mensagem genérica e nunca autenticam.
     */
    public function test_login_credential_rejections(): void
    {
        User::factory()->create([
            'email' => 'aluno@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $inactive = User::factory()->create([
            'email' => 'inativo@example.com',
            'password' => bcrypt('correct-password'),
            'status' => 'inactive',
        ]);
        $inactive->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser): void {
            // 1. Senha errada.
            $browser->visit('/login')
                ->type('@login-email', 'aluno@example.com')
                ->type('@login-password', 'totally-wrong-password')
                ->press('@login-submit')
                ->waitForText('Essas credenciais não foram encontradas em nossos registros.')
                ->assertGuest();

            // 2. Usuário inativo, senha correta, mesma tela.
            $browser->type('@login-email', 'inativo@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForText('Essas credenciais não foram encontradas em nossos registros.')
                ->assertGuest();
        });
    }

    /**
     * O botão eye/eye-off (`resources/js/modules/PasswordToggle.js`) alterna
     * `type`/`aria-label` do campo de senha e os ícones show/hide — nenhum
     * `dusk=` cobre esse contrato (a lista de 388 seletores é imutável), por
     * isso a asserção usa os seletores CSS reais que o módulo consulta.
     */
    public function test_password_toggle_reveals_and_hides_the_password_field(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/login')
                ->assertAttribute('input[name=password]', 'type', 'password')
                ->assertAttribute('[data-password-toggle-btn]', 'aria-label', 'Mostrar senha')
                ->assertVisible('[data-password-toggle-icon="show"]')
                ->assertMissing('[data-password-toggle-icon="hide"]:not(.d-none)')
                ->click('[data-password-toggle-btn]')
                ->assertAttribute('input[name=password]', 'type', 'text')
                ->assertAttribute('[data-password-toggle-btn]', 'aria-label', 'Ocultar senha')
                ->assertVisible('[data-password-toggle-icon="hide"]')
                ->assertMissing('[data-password-toggle-icon="show"]:not(.d-none)')
                ->click('[data-password-toggle-btn]')
                ->assertAttribute('input[name=password]', 'type', 'password')
                ->assertAttribute('[data-password-toggle-btn]', 'aria-label', 'Mostrar senha');
        });
    }

    public function test_password_reset_lifecycle(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('old-password'),
        ]);
        $user->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($user): void {
            // 1. Solicitação do link de recuperação.
            $browser->visit('/forgot-password')
                ->assertPresent('@forgot-password-form')
                ->type('@forgot-password-email', 'reset@example.com')
                ->press('@forgot-password-submit')
                ->waitForText('Enviamos o link de redefinição de senha para o seu e-mail.');

            $this->assertDatabaseHas('password_reset_tokens', [
                'email' => 'reset@example.com',
            ]);

            // 2. Token inválido é rejeitado e não troca a senha.
            $browser->visit(route('password.reset', 'token-totalmente-invalido'))
                ->assertPresent('@reset-password-form')
                ->type('@reset-password-email', 'reset@example.com')
                ->type('@reset-password-password', 'new-password-123')
                ->type('@reset-password-password-confirmation', 'new-password-123')
                ->press('@reset-password-submit')
                ->waitForText('Este token de redefinição de senha é inválido.');

            $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

            // 3. Token válido troca a senha e o login novo funciona.
            $token = Password::broker()->createToken($user);

            $browser->visit(route('password.reset', $token))
                ->assertPresent('@reset-password-form')
                ->type('@reset-password-email', 'reset@example.com')
                ->type('@reset-password-password', 'new-password-123')
                ->type('@reset-password-password-confirmation', 'new-password-123')
                ->press('@reset-password-submit')
                ->waitForLocation('/login')
                ->waitForText('Sua senha foi redefinida com sucesso.')
                ->type('@login-email', 'reset@example.com')
                ->type('@login-password', 'new-password-123')
                ->press('@login-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticatedAs($user);
        });

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }
}
