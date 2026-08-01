<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 *
 * `org_id` is intentionally left out of the default definition (see
 * `tenancy-conventions`): callers must set it explicitly, either via
 * `->for(Organization::factory())` / `->create(['org_id' => $org->id])`,
 * or by leaving it unset so `OrgScope::booted()`'s `creating` hook
 * auto-assigns/validates it from the acting user's session context.
 */
class CourseFactory extends Factory
{
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
     * Indicate that the course is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }
}
