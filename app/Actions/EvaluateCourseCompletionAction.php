<?php

namespace App\Actions;

use App\Events\CourseCompletedByStudent;
use App\Models\Course;
use App\Models\User;

/**
 * Recomputes a single student's `course_user.progress_percentage` for the
 * given Course (published, non-deleted Lessons only) and — when the
 * `all_lessons` `course_completion_rules` threshold is met — marks the
 * enrollment `completed` and dispatches `CourseCompletedByStudent`, the
 * event `IssueCertificateOnCourseCompletion` listens to.
 *
 * Extracted from the `RecalculateCourseProgress` listener so the same
 * evaluation can be applied RETROACTIVELY: when a Gestor creates a
 * completion rule, students who already reached the threshold before any
 * rule existed (and therefore never fired the lesson-completion pipeline)
 * are evaluated right there in `CourseCompletionRuleController::store`.
 *
 * Dispatch discipline: `CourseCompletedByStudent` fires only on the
 * `active` → `completed` TRANSITION. Re-evaluating an already-completed
 * student never re-dispatches (downstream issuance is idempotent via
 * `IssueCertificateAction`, but there is no reason to re-run it), and
 * never overwrites the original `completed_at`.
 */
class EvaluateCourseCompletionAction
{
    public function execute(Course $course, User $user): void
    {
        $pivot = $course->students()->where('user_id', $user->id)->first()?->pivot;

        if ($pivot === null) {
            return;
        }

        $totalPublishedLessons = $course->publishedLessonsCountFor();
        $completedLessons = $course->completedLessonsCountFor($user);

        $percentage = $totalPublishedLessons > 0
            ? (int) round($completedLessons / $totalPublishedLessons * 100)
            : 0;

        $rule = $course->completionRules()
            ->where('rule_type', 'all_lessons')
            ->first();

        $shouldCompleteCourse = $rule !== null
            && $percentage >= $rule->required_percentage;

        $isCompletionTransition = $shouldCompleteCourse
            && $pivot->status !== 'completed';

        $pivotUpdate = ['progress_percentage' => $percentage];

        if ($isCompletionTransition) {
            $pivotUpdate['status'] = 'completed';
            $pivotUpdate['completed_at'] = now();
        }

        $course->students()->updateExistingPivot($user->id, $pivotUpdate);

        if ($isCompletionTransition) {
            CourseCompletedByStudent::dispatch($course, $user);
        }
    }
}
