<?php

namespace Database\Factories;

use App\Models\ForumTopic;
use App\Models\Organization;
use Database\Factories\Concerns\ResolvesInOrg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumTopic>
 *
 * `org_id` is intentionally left out of the default definition (see
 * `tenancy-conventions`): callers must set it explicitly, either via
 * `->inOrg($org)` or by leaving it unset so `OrgScope::booted()`'s
 * `creating` hook auto-assigns/validates it from the acting user's session
 * context. `course_id`/`user_id` are also left out — callers set them
 * explicitly via `->for(Course::factory())` / `->for(User::factory())`.
 */
class ForumTopicFactory extends Factory
{
    use ResolvesInOrg;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'is_pinned' => false,
            'edited_at' => null,
        ];
    }

    /**
     * Pin the topic to an Organization (`null` keeps `org_id` unset,
     * letting the `creating` hook resolve it when a user is acting).
     */
    public function inOrg(Organization|int|null $organization): static
    {
        return $this->state(fn (): array => [
            'org_id' => self::resolveInOrg($organization)?->id,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => ['is_pinned' => true]);
    }
}
