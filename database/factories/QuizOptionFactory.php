<?php

namespace Database\Factories;

use App\Models\QuizOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizOption>
 *
 * `question_id` is intentionally left out of the default definition —
 * callers set it explicitly via `->for(QuizQuestion::factory(), 'question')`
 * / `->create(['question_id' => $question->id])`.
 */
class QuizOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_text' => fake()->words(3, true),
            'is_correct' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => ['is_correct' => true]);
    }

    public function incorrect(): static
    {
        return $this->state(fn (array $attributes) => ['is_correct' => false]);
    }
}
