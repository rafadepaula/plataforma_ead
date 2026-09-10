<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Organization;
use Database\Factories\Concerns\ResolvesInOrg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 *
 * `org_id` is intentionally left out of the default definition (see
 * `tenancy-conventions`): callers must set it explicitly, either via
 * `->inOrg($org)` / `->for(Organization::factory())`, or by leaving it
 * unset so `OrgScope::booted()`'s `creating` hook auto-assigns/validates
 * it from the acting user's session context.
 */
class CourseFactory extends Factory
{
    use ResolvesInOrg;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'workload_hours' => fake()->numberBetween(1, 200),
            'is_published' => false,
        ];
    }

    /**
     * Pin the course to an Organization (`null` keeps `org_id` unset,
     * letting the `creating` hook resolve it when a user is acting).
     */
    public function inOrg(Organization|int|null $organization): static
    {
        return $this->state(fn (): array => [
            'org_id' => self::resolveInOrg($organization)?->id,
        ]);
    }

    /**
     * Indicate that the course is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }
}
