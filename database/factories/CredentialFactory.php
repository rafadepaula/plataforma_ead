<?php

namespace Database\Factories;

use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Default `org_id = null` means the global Admin account; pass
     * `org_id` (or use `forOrg()`) to create an organization account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'org_id' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Bind the account to the given Organization (`null` = global Admin
     * account).
     */
    public function forOrg(?Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'org_id' => $organization?->id,
        ]);
    }

    /**
     * Indicate that the account is inactive (login fails even with the
     * correct password).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the account was created by the Gestor but the Aluno
     * has not finalized it through their unique invitation link yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Set a known plain-text password (default: "password").
     */
    public function withPassword(#[\SensitiveParameter] string $password): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make($password),
        ]);
    }
}
