<?php

namespace Database\Factories;

use App\Models\CourseCompletionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseCompletionRule>
 *
 * `course_id` is intentionally left out of the default definition (mirrors
 * `ModuleFactory`'s convention): callers set it explicitly via
 * `->for(Course::factory())` / `->create(['course_id' => $course->id])`.
 */
class CourseCompletionRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_type' => 'all_lessons',
            'target_id' => null,
            'required_percentage' => 100,
        ];
    }

    /**
     * Eligible once `course_user.progress_percentage` reaches
     * `required_percentage` (default 100).
     */
    public function allLessons(int $requiredPercentage = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'all_lessons',
            'target_id' => null,
            'required_percentage' => $requiredPercentage,
        ]);
    }

    /**
     * Eligible once the student's best `quiz_attempts.score_percentage`
     * for the Quiz identified by `target_id` reaches `required_percentage`.
     */
    public function minQuizScore(int $targetId, int $requiredPercentage = 70): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'min_quiz_score',
            'target_id' => $targetId,
            'required_percentage' => $requiredPercentage,
        ]);
    }

    /**
     * Eligible once every Lesson of the Module identified by `target_id`
     * has `lesson_progress.is_completed = true` for the student.
     */
    public function specificModule(int $targetId): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'specific_module',
            'target_id' => $targetId,
            'required_percentage' => 100,
        ]);
    }
}
