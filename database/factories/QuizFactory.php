<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 *
 * `lesson_id` is intentionally left out of the default definition (mirrors
 * `LessonFactory`'s `module_id` convention): callers set it explicitly via
 * `->for(Lesson::factory())` / `->create(['lesson_id' => $lesson->id])`,
 * since `quizzes.lesson_id` is unique (1:1) and a bare `Quiz::factory()->
 * create()` would otherwise create a dangling row with no parent Lesson.
 */
class QuizFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraph(),
            'allow_retries' => true,
            'max_attempts' => null,
            'time_limit_minutes' => null,
            'show_correct_answers' => false,
            'min_score_percentage' => 70,
        ];
    }
}
