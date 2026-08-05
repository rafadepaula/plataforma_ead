<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 *
 * `user_id`/`course_id` are intentionally left out of the default
 * definition (mirrors `QuizAttemptFactory`'s convention): callers set them
 * explicitly via `->for(...)` / `->create([...])`, since `certificates`
 * has a `UNIQUE(user_id, course_id)` constraint a bare
 * `Certificate::factory()->create()` could otherwise collide with.
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'validation_hash' => hash('sha256', Str::uuid()->toString()),
            'issued_at' => now(),
            'revoked_at' => null,
            'revoked_by' => null,
            'revoke_reason' => null,
        ];
    }

    /**
     * A certificate already revoked by the given (or a fresh) User.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'revoked_by' => UserFactory::new(),
            'revoke_reason' => fake()->sentence(6),
        ]);
    }
}
