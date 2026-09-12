<?php

namespace Database\Factories;

use App\Models\StudentInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentInvitation>
 *
 * `org_id`/`user_id`/`created_by` are intentionally left out of the
 * default definition (mirrors `CourseFactory`'s convention): callers set
 * them explicitly via `->for(...)` or `->create([...])`.
 */
class StudentInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(64),
            'expires_at' => null,
            'used_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the invitation's `expires_at` is in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has already been redeemed by the Aluno.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has been revoked by a Gestor/Admin.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
