<?php

namespace Tests\Feature\Auth;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class HostScopedLoginTest extends TestCase
{
    public function test_aluno_loga_no_host_da_org_onde_tem_conta(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

        $this->onHost('portal.acme.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertRedirect(route('student.courses.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_mesma_pessoa_sem_conta_no_host_b_falha_com_erro_generico(): void
    {
        $organizationA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organizationA->id]);
        Organization::factory()->create(['host' => 'portal.bsb.test']);

        $this->onHost('portal.bsb.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_senha_errada_falha_generico(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

        $this->onHost('portal.acme.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_credential_inativa_falha(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->inactive()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

        $this->onHost('portal.acme.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_em_org_inativa_falha_mesmo_com_credential_valida(): void
    {
        Organization::factory()->inactive()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create([
            'user_id' => $user->id,
            'org_id' => Organization::query()->where('host', 'portal.acme.test')->firstOrFail()->id,
        ]);

        $this->onHost('portal.acme.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_estado_zero_so_admin_loga(): void
    {
        $admin = User::factory()->create(['email' => 'root@system.test']);
        $admin->assignRole(RolesEnum::ADMIN->value);
        Credential::factory()->create(['user_id' => $admin->id, 'org_id' => null]);

        $this->onHost('127.0.0.1')
            ->post('/login', ['email' => 'root@system.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_estado_zero_rejeita_conta_de_org(): void
    {
        $organization = Organization::factory()->create(['host' => 'portal.acme.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

        $this->onHost('127.0.0.1')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_throttle_e_por_org(): void
    {
        $organizationA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $organizationB = Organization::factory()->create(['host' => 'portal.bsb.test']);
        $user = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organizationA->id]);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organizationB->id]);

        foreach (range(1, 5) as $attempt) {
            $this->onHost('portal.acme.test')
                ->post('/login', ['email' => 'ana@acme.test', 'password' => 'wrong']);
        }

        // 5 tentativas no host A: a 6ª cai no throttle ANTES de validar —
        // nem a senha certa passa ali.
        $this->onHost('portal.acme.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        // Host B (outra org do throttle) segue aceitando a mesma pessoa.
        $this->onHost('portal.bsb.test')
            ->post('/login', ['email' => 'ana@acme.test', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }
}
