<?php

namespace Database\Factories;

use App\Models\QuizAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAnswer>
 *
 * `attempt_id`/`question_id` are intentionally left out of the default
 * definition — callers set them explicitly via `->for(...)` /
 * `->create([...])`.
 */
class QuizAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'selected_option_ids' => [],
            'essay_answer' => null,
            'is_correct' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];
    }

    /**
     * An auto-graded objective answer (single/multiple choice, true/false).
     *
     * @param  list<int>  $optionIds
     */
    public function withSelectedOptions(array $optionIds, bool $isCorrect): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_option_ids' => $optionIds,
            'essay_answer' => null,
            'is_correct' => $isCorrect,
        ]);
    }

    /**
     * An `essay` answer, still awaiting manual grading.
     */
    public function essay(?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_option_ids' => null,
            'essay_answer' => $text ?? fake()->paragraph(),
            'is_correct' => null,
            'graded_by' => null,
            'graded_at' => null,
        ]);
    }
}
