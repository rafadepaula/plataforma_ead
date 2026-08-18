<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_rendered_in_brazilian_portuguese(): void
    {
        $response = $this->post(route('login'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'O campo e-mail é obrigatório.',
            'password' => 'O campo senha é obrigatório.',
        ]);
    }

    public function test_auth_failed_message_is_in_brazilian_portuguese(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Essas credenciais não foram encontradas em nossos registros.',
        ]);
    }

    public function test_password_broker_messages_are_in_brazilian_portuguese(): void
    {
        $this->assertSame(
            'Não encontramos nenhum usuário com esse endereço de e-mail.',
            trans(Password::INVALID_USER)
        );

        $this->assertSame(
            'Enviamos o link de redefinição de senha para o seu e-mail.',
            trans(Password::RESET_LINK_SENT)
        );

        $this->assertSame(
            'Este token de redefinição de senha é inválido.',
            trans(Password::INVALID_TOKEN)
        );

        $this->assertSame(
            'Sua senha foi redefinida com sucesso.',
            trans(Password::PASSWORD_RESET)
        );
    }

    public function test_pagination_messages_are_in_brazilian_portuguese(): void
    {
        $this->assertSame('&laquo; Anterior', trans('pagination.previous'));
        $this->assertSame('Próximo &raquo;', trans('pagination.next'));
    }
}
