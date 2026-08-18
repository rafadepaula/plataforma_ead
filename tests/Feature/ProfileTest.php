<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * any authenticated user managing their own profile
 * (`name`/`email`/`cpf`). `org_id` and `status` are never mutable via this
 * endpoint (RN08/RN12), unlike the Admin/Gestor-only `UserController`.
 */
class ProfileTest extends TestCase
{
    public function test_authenticated_user_can_view_their_own_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/profile')
            ->assertOk()
            ->assertViewIs('profile.edit')
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_guest_visiting_profile_is_redirected_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_update_their_name_email_and_cpf(): void
    {
        $user = User::factory()->create(['cpf' => null]);
        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => 'Novo Nome',
            'email' => 'novo.email@example.com',
            'cpf' => '52998224725',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success', 'Perfil atualizado com sucesso.');

        $user->refresh();
        $this->assertSame('Novo Nome', $user->name);
        $this->assertSame('novo.email@example.com', $user->email);
        $this->assertSame('52998224725', $user->cpf);
    }

    public function test_updating_to_an_email_already_used_by_another_user_fails_validation(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
            'cpf' => null,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('mine@example.com', $user->fresh()->email);
    }

    public function test_updating_to_a_cpf_already_used_by_another_user_fails_validation(): void
    {
        $user = User::factory()->create(['cpf' => null]);
        $other = User::factory()->create(['cpf' => '52998224725']);
        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => '52998224725',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertNull($user->fresh()->cpf);
    }

    public function test_updating_with_an_invalid_checksum_cpf_fails_validation(): void
    {
        $user = User::factory()->create(['cpf' => null]);
        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => '11111111111',
        ]);

        $response->assertSessionHasErrors(['cpf' => 'O CPF informado é inválido.']);
    }

    public function test_changing_email_does_not_reset_email_verified_at(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now()->subDay(),
        ]);
        $verifiedAt = $user->email_verified_at;
        $this->actingAs($user);

        $this->patch('/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
            'cpf' => null,
        ]);

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
    }

    public function test_profile_update_never_mutates_org_id_or_status(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $user = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => null,
            'org_id' => $otherOrg->id,
            'status' => 'inactive',
        ]);

        $user->refresh();
        $this->assertSame($org->id, $user->org_id);
        $this->assertSame('active', $user->status);
    }
}
