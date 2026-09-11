<?php

namespace Tests\Feature\Auth;

use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetHostTest extends TestCase
{
    public function test_reset_troca_a_senha_da_org_do_host_e_preserva_a_outra(): void
    {
        $orgA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $orgB = Organization::factory()->create(['host' => 'portal.bsb.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        $credentialA = Credential::factory()->create(['user_id' => $user->id, 'org_id' => $orgA->id]);
        $credentialB = Credential::factory()->create(['user_id' => $user->id, 'org_id' => $orgB->id]);

        $token = Password::createToken($user);

        $this->onHost('portal.acme.test')
            ->post('/reset-password', [
                'token' => $token,
                'email' => 'ana@acme.test',
                'password' => 'nova-senha-forte-1',
                'password_confirmation' => 'nova-senha-forte-1',
            ]);

        $this->assertTrue(Hash::check('nova-senha-forte-1', $credentialA->fresh()->password));
        $this->assertTrue(Hash::check('password', $credentialB->fresh()->password));
    }

    public function test_reset_com_token_pre_existente_e_bloqueado_em_org_inativa(): void
    {
        $org = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $org->id]);

        $this->onHost('portal.acme.test');

        // token emitido QUANDO a org ainda era ativa
        $token = Password::createToken($user);
        $org->forceFill(['status' => 'inactive'])->save();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'ana@acme.test',
            'password' => 'nova-senha-forte-1',
            'password_confirmation' => 'nova-senha-forte-1',
        ])->assertSessionHasErrors('email');

        // falha genérica: a senha da conta permanece
        $this->assertTrue(Hash::check('password', Credential::find($user->credentialFor($org)->id)->password));
    }

    public function test_forgot_sem_conta_na_org_do_host_falha_generico(): void
    {
        $orgA = Organization::factory()->create(['host' => 'portal.acme.test']);
        Organization::factory()->create(['host' => 'portal.bsb.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $orgA->id]);

        $this->onHost('portal.bsb.test')
            ->post('/forgot-password', ['email' => 'ana@acme.test'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_forgot_em_org_inativa_falha_generico(): void
    {
        Organization::factory()->inactive()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);

        $this->onHost('portal.acme.test')
            ->post('/forgot-password', ['email' => 'ana@acme.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_do_estado_zero_consegue_resetar(): void
    {
        $admin = User::factory()->create(['email' => 'root@system.test']);
        Credential::factory()->create(['user_id' => $admin->id, 'org_id' => null]);

        $this->onHost('127.0.0.1')
            ->post('/forgot-password', ['email' => 'root@system.test']);

        $status = Password::getRepository()->recentlyCreatedToken($admin);
        $this->assertTrue($status);
    }
}
