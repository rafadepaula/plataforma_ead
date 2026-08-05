<?php

namespace Database\Factories;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'org_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'cpf' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'remember_token' => Str::random(10),
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
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Attach a unique CPF (SPEC-00 §2.1.2) to the user.
     */
    public function withCpf(): static
    {
        return $this->state(fn (array $attributes) => [
            'cpf' => fake()->unique()->numerify('###########'),
        ]);
    }

    /**
     * RF04 — assign the `aluno` Spatie role after creation. Does not force
     * `org_id`: an aluno may be `org_id = null` until enrolled via
     * `course_user` (see `App\Models\User`'s docblock).
     */
    public function aluno(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RolesEnum::ALUNO->value);
        });
    }

    /**
     * RF04 — assign the `gestor` Spatie role after creation. A gestor
     * always carries an `org_id`; one is auto-created if not already set.
     */
    public function gestor(): static
    {
        return $this->state(fn (array $attributes) => [
            'org_id' => $attributes['org_id'] ?? Organization::factory(),
        ])->afterCreating(function (User $user): void {
            $user->assignRole(RolesEnum::GESTOR->value);
        });
    }
}
