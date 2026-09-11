<?php

namespace Database\Factories;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Database\Factories\Concerns\ResolvesInOrg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    use ResolvesInOrg;

    /**
     * Define the model's default state.
     *
     * A `User` is only the person identity — account data lives in
     * `Credential` (see `CredentialFactory`). Attach a credential
     * explicitly (`Credential::factory()->create([...])`) or through the
     * `actingAsOrgUser()` test helper.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'cpf' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give the person an account (credential) in the given Organization
     * after creation — `null` means the global admin credential
     * (`credentials.org_id = null`, valid on every host, including the
     * state-zero login).
     */
    public function inOrg(Organization|int|null $organization): static
    {
        return $this->afterCreating(function (User $user) use ($organization): void {
            Credential::factory()
                ->forOrg(self::resolveInOrg($organization))
                ->create(['user_id' => $user->id]);
        });
    }

    /**
     * Deactivate every account (credential) of this person — the
     * person-level "kill switch" is "no active credentials".
     */
    public function inactive(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->credentials()->update(['status' => 'inactive']);
        });
    }

    /**
     * Attach a unique CPF to the user.
     */
    public function withCpf(): static
    {
        return $this->state(fn (array $attributes) => [
            'cpf' => fake()->unique()->numerify('###########'),
        ]);
    }

    /**
     * Set the password on every credential of this person (created after
     * `inOrg()` in the chain, so the credential already exists).
     */
    public function withPassword(string $password): static
    {
        return $this->afterCreating(function (User $user) use ($password): void {
            // `withPassword()` não cria conta: sem `inOrg()` antes, a senha
            // se perderia silenciosamente — falhar alto é o contrato
            if (! $user->credentials()->exists()) {
                throw new LogicException(
                    'UserFactory::withPassword() requer inOrg() antes na cadeia (a credential precisa existir).'
                );
            }

            $user->credentials->each(
                fn (Credential $credential) => $credential->update(['password' => $password])
            );
        });
    }

    /**
     * Assign the `aluno` Spatie role after creation. No credential is
     * created here — students hold one account per Organization and tests
     * attach them explicitly.
     */
    public function aluno(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RolesEnum::ALUNO->value);
        });
    }

    /**
     * Assign the `gestor` role and, when the person holds no account yet,
     * create one in a fresh Organization (so a bare
     * `User::factory()->gestor()->create()` is still usable in isolation).
     */
    public function gestor(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! $user->credentials()->exists()) {
                Credential::factory()
                    ->forOrg(Organization::factory()->create())
                    ->create(['user_id' => $user->id]);
            }

            $user->assignRole(RolesEnum::GESTOR->value);
        });
    }

    /**
     * Assign the `professor` role and, when the person holds no account
     * yet, create one in a fresh Organization. Course assignments are NOT
     * made here — tests attach them explicitly through
     * `Course::professors()`.
     */
    public function professor(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! $user->credentials()->exists()) {
                Credential::factory()
                    ->forOrg(Organization::factory()->create())
                    ->create(['user_id' => $user->id]);
            }

            $user->assignRole(RolesEnum::PROFESSOR->value);
        });
    }
}
