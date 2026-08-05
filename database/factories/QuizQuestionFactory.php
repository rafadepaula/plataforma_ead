<?php

namespace Database\Factories;

use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 *
 * `quiz_id` is intentionally left out of the default definition — callers
 * set it explicitly via `->for(Quiz::factory())` / `->create(['quiz_id' =>
 * $quiz->id])`.
 */
class QuizQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_text' => fake()->sentence().'?',
            'type' => 'single_choice',
            'order_index' => 0,
        ];
    }

    public function singleChoice(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'single_choice']);
    }

    public function multipleChoice(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'multiple_choice']);
    }

    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'true_false']);
    }

    public function essay(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'essay']);
    }
}
