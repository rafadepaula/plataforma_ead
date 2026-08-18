<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 *
 * `quiz_id`/`user_id` are intentionally left out of the default
 * definition — callers set them explicitly via `->for(...)` /
 * `->create([...])`.
 */
class QuizAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'score_percentage' => null,
            'is_passed' => null,
            'status' => 'in_progress',
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * Still being taken — no `score_percentage`/`is_passed`/`completed_at`
     * yet.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'score_percentage' => null,
            'is_passed' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Auto-graded questions are done, but at least 1 `essay` answer is
     * still pending a Gestor's manual grade .
     */
    public function awaitingManualGrading(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'awaiting_manual_grading',
            'score_percentage' => null,
            'is_passed' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * Fully graded — either purely auto-graded, or manual essay grading
     * has finalized .
     */
    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'score_percentage' => fake()->randomFloat(2, 0, 100),
            'is_passed' => fake()->boolean(),
            'completed_at' => now(),
        ]);
    }
}
