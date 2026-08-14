<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-18 (UC02/RF01/RF34) — E2E coverage of the profile self-service
 * screen (`profile.edit`/`profile.update`/`password.update`).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de autoatendimento (editar dados → trocar senha) é um método, e
 * as três rejeições inline (e-mail duplicado, CPF com checksum inválido,
 * `current_password` errada) são exercitadas na MESMA sessão de formulário.
 * O redirect do visitante é negativa independente.
 */
class ProfileTest extends DuskTestCase
{
    public function test_user_profile_and_password_update_lifecycle(): void
    {
        $user = User::factory()->create(['cpf' => null]);

        $this->browse(function (Browser $browser) use ($user): void {
            // 1. Edição dos dados cadastrais
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@profile-form')
                ->clear('name')
                ->type('name', 'Novo Nome do Usuário')
                ->clear('email')
                ->type('email', 'novo.email@example.com')
                ->clear('cpf')
                ->type('cpf', '52998224725')
                ->waitForReload(fn (Browser $b) => $b->click('@profile-submit'))
                ->waitForText('Perfil atualizado com sucesso.')
                ->assertInputValue('name', 'Novo Nome do Usuário')
                ->assertInputValue('email', 'novo.email@example.com');

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => 'Novo Nome do Usuário',
                'email' => 'novo.email@example.com',
                'cpf' => '52998224725',
            ]);

            // 2. Troca de senha, na mesma tela e mesma sessão
            $browser->waitFor('@password-form')
                ->type('current_password', 'password')
                ->type('password', 'NovaSenhaForte123!')
                ->type('password_confirmation', 'NovaSenhaForte123!')
                ->waitForReload(fn (Browser $b) => $b->click('@password-submit'))
                ->waitForText('Senha alterada com sucesso.');
        });

        $user->refresh();
        $this->assertTrue(Hash::check('NovaSenhaForte123!', $user->password));
    }

    /**
     * As três rejeições inline exercitadas em sequência na mesma sessão de
     * formulário: nenhuma delas pode redirecionar para fora de `/profile`,
     * e nenhuma pode persistir dado algum.
     */
    public function test_profile_form_inline_validation_rejections(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create(['cpf' => null]);
        $originalEmail = $user->email;

        $this->browse(function (Browser $browser) use ($user): void {
            // 1. E-mail já usado por outro usuário
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@profile-form')
                ->clear('email')
                ->type('email', 'ocupado@example.com')
                ->waitForReload(fn (Browser $b) => $b->click('@profile-submit'))
                ->assertPathIs('/profile')
                ->assertSee('already been taken');

            // 2. CPF com checksum inválido (UC02 §6.2 — fluxo de exceção
            //    próprio, não confundir com o duplicado do §6.1).
            $browser->waitFor('@profile-form')
                ->clear('cpf')
                ->type('cpf', '52998224726')
                ->waitForReload(fn (Browser $b) => $b->click('@profile-submit'))
                ->assertPathIs('/profile')
                ->assertSee('O CPF informado é inválido.');

            // 3. `current_password` errada no formulário de senha
            $browser->waitFor('@password-form')
                ->type('current_password', 'senha-errada-qualquer')
                ->type('password', 'OutraSenhaForte123!')
                ->type('password_confirmation', 'OutraSenhaForte123!')
                ->waitForReload(fn (Browser $b) => $b->click('@password-submit'))
                ->assertPathIs('/profile')
                ->assertSee('password is incorrect');
        });

        $user->refresh();
        $this->assertSame($originalEmail, $user->email);
        $this->assertNull($user->cpf);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_guest_visiting_profile_is_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/profile')
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }
}
