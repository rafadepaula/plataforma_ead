<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;

/**
 * Single owner of "does this student satisfy this course's completion
 * rules?" — shared by `EvaluateCourseCompletionAction` (marks the
 * enrollment `completed`) and `IssueCertificateAction` (issues the
 * certificate), so the two can never disagree about what "concluded"
 * means.
 *
 * Completion is **literally** the registered `course_completion_rules`
 * rows: every row must pass (AND, never OR), and `all_lessons` is just
 * one parametrized rule among them — not a mandatory gate. A course
 * with zero rules never completes anyone.
 *
 * `$progressOverride` exists because `EvaluateCourseCompletionAction`
 * recomputes the percentage in-memory before persisting it; passing the
 * fresh value keeps the `all_lessons` check from reading the stale
 * pivot. Callers that already persisted (or never recompute) omit it
 * and the pivot value is used.
 */
class CourseCompletionEvaluator
{
    public function satisfied(Course $course, User $user, ?int $progressOverride = null): bool
    {
        $rules = $course->completionRules()->get();

        if ($rules->isEmpty()) {
            return false;
        }

        foreach ($rules as $rule) {
            if (! $this->ruleSatisfied($rule, $course, $user, $progressOverride)) {
                return false;
            }
        }

        return true;
    }

    public function ruleSatisfied(
        CourseCompletionRule $rule,
        Course $course,
        User $user,
        ?int $progressOverride = null,
    ): bool {
        return match ($rule->rule_type) {
            'all_lessons' => $this->allLessonsSatisfied($rule, $course, $user, $progressOverride),
            'min_quiz_score' => $this->minQuizScoreSatisfied($rule, $user),
            'specific_module' => $this->specificModuleSatisfied($rule, $user),
            default => false,
        };
    }

    /**
     * `course_user.progress_percentage >= required_percentage` for this
     * student. Missing/no enrollment pivot is treated as 0%.
     */
    private function allLessonsSatisfied(
        CourseCompletionRule $rule,
        Course $course,
        User $user,
        ?int $progressOverride = null,
    ): bool {
        $progress = $progressOverride;

        if ($progress === null) {
            $enrollment = $course->students()->where('users.id', $user->id)->first();
            $progress = $enrollment?->pivot?->progress_percentage ?? 0;
        }

        return $progress >= $rule->required_percentage;
    }

    /**
     * `target_id` points to `quizzes.id`; a `target_id` that no longer
     * resolves (deleted quiz) is treated as not-satisfied rather than
     * throwing.
     */
    private function minQuizScoreSatisfied(CourseCompletionRule $rule, User $user): bool
    {
        $quiz = Quiz::find($rule->target_id);

        if (! $quiz) {
            return false;
        }

        $bestScore = $user->bestQuizScoreFor($quiz);

        return $bestScore !== null && $bestScore >= $rule->required_percentage;
    }

    /**
     * `target_id` points to `modules.id`; a `target_id` that no longer
     * resolves (deleted module) is treated as not-satisfied rather than
     * throwing. Every Lesson of the target Module must have
     * `lesson_progress.is_completed = true` for this student.
     */
    private function specificModuleSatisfied(CourseCompletionRule $rule, User $user): bool
    {
        $module = Module::find($rule->target_id);

        if (! $module) {
            return false;
        }

        $lessonIds = $module->lessons()->pluck('id');

        if ($lessonIds->isEmpty()) {
            return false;
        }

        $completedCount = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('is_completed', true)
            ->count();

        return $completedCount === $lessonIds->count();
    }
}
