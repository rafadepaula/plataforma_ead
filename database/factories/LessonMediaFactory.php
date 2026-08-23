<?php

namespace Database\Factories;

use App\Models\LessonMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonMedia>
 *
 * `lesson_id` is intentionally left out of the definition (mirrors the
 * `LessonFactory` convention): callers set it explicitly via
 * `->for(Lesson::factory(), 'lesson')` / `->create(['lesson_id' => ...])`.
 *
 * The default definition is an image attachment; use the `pdf()` state for
 * PDFs.
 */
class LessonMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => LessonMedia::KIND_IMAGE,
            'path' => 'orgs/1/courses/1/images/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }

    /**
     * Image attachment.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => LessonMedia::KIND_IMAGE,
            'path' => 'orgs/1/courses/1/images/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
        ]);
    }

    /**
     * PDF attachment.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => LessonMedia::KIND_PDF,
            'path' => 'orgs/1/courses/1/pdfs/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
        ]);
    }
}
