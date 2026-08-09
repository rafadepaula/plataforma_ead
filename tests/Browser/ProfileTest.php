<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-18 (UC02/RF01/RF34) — E2E coverage of the profile self-service
 * screen (`profile.edit`/`profile.update`/`password.update`): editing
 * cadastral data, changing the password, duplicate email/cpf rejection,
 * wrong `current_password` rejection, and the guest redirect (RN08).
 */
class ProfileTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_edits_name_email_and_cpf_successfully(): void
    {
        $user = User::factory()->create(['cpf' => null]);

        $this->browse(function (Browser $browser) use ($user): void {
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
        });

        $user->refresh();
        $this->assertSame('Novo Nome do Usuário', $user->name);
        $this->assertSame('novo.email@example.com', $user->email);
        $this->assertSame('52998224725', $user->cpf);
    }

    public function test_user_updates_password_successfully(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@password-form')
                ->type('current_password', 'password')
                ->type('password', 'NovaSenhaForte123!')
                ->type('password_confirmation', 'NovaSenhaForte123!')
                ->waitForReload(fn (Browser $b) => $b->click('@password-submit'))
                ->waitForText('Senha alterada com sucesso.');
        });

        $user->refresh();
        $this->assertTrue(Hash::check('NovaSenhaForte123!', $user->password));
    }

    public function test_duplicate_email_shows_inline_validation_error_without_redirecting_away(): void
    {
        $existing = User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@profile-form')
                ->clear('email')
                ->type('email', 'ocupado@example.com')
                ->waitForReload(fn (Browser $b) => $b->click('@profile-submit'))
                ->assertPathIs('/profile')
                ->assertSee('already been taken');
        });

        $user->refresh();
        $this->assertNotSame('ocupado@example.com', $user->email);
    }

    public function test_checksum_invalid_cpf_shows_inline_validation_error_without_redirecting_away(): void
    {
        $user = User::factory()->create(['cpf' => null]);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@profile-form')
                ->clear('cpf')
                ->type('cpf', '52998224726')
                ->waitForReload(fn (Browser $b) => $b->click('@profile-submit'))
                ->assertPathIs('/profile')
                ->assertSee('O CPF informado é inválido.');
        });

        $user->refresh();
        $this->assertNull($user->cpf);
    }

    public function test_wrong_current_password_shows_inline_error_and_password_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('profile.edit'))
                ->waitFor('@password-form')
                ->type('current_password', 'senha-errada-qualquer')
                ->type('password', 'OutraSenhaForte123!')
                ->type('password_confirmation', 'OutraSenhaForte123!')
                ->waitForReload(fn (Browser $b) => $b->click('@password-submit'))
                ->assertPathIs('/profile')
                ->assertSee('password is incorrect');
        });

        $user->refresh();
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
