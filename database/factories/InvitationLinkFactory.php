<?php

namespace Database\Factories;

use App\Models\InvitationLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvitationLink>
 *
 * `org_id`/`course_id`/`created_by` are intentionally left out of the
 * default definition (mirrors `CourseFactory`'s convention): callers set
 * them explicitly via `->for(...)` or `->create([...])`.
 */
class InvitationLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(64),
            'max_uses' => null,
            'current_uses' => 0,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the link's `expires_at` is in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the link has already reached its `max_uses` cap.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => 1,
            'current_uses' => 1,
        ]);
    }

    /**
     * Indicate that the link has been revoked by a Gestor/Admin.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
