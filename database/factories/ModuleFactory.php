<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 *
 * `course_id` is intentionally left out of the default definition (mirrors
 * `CourseFactory`'s `org_id` convention): callers set it explicitly via
 * `->for(Course::factory())` / `->create(['course_id' => $course->id])`.
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'order_index' => 0,
        ];
    }
}
