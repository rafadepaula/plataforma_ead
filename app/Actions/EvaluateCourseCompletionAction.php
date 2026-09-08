<?php

namespace App\Actions;

use App\Events\CourseCompletedByStudent;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseCompletionEvaluator;

/**
 * Recomputes a single student's `course_user.progress_percentage` for the
 * given Course (published, non-deleted Lessons only) and — when **every**
 * registered `course_completion_rules` row is satisfied (AND) — marks the
 * enrollment `completed` and dispatches `CourseCompletedByStudent`, the
 * event `IssueCertificateOnCourseCompletion` listens to.
 *
 * `all_lessons` is just one parametrized rule among the three — never a
 * mandatory gate. A course with zero rules only gets its percentage
 * recomputed, never completed.
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
    public function __construct(
        protected CourseCompletionEvaluator $evaluator = new CourseCompletionEvaluator,
    ) {}

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

        // The fresh percentage is passed through so the `all_lessons`
        // check (when registered) reads this request's value instead of
        // the still-stale pivot about to be overwritten below.
        $shouldCompleteCourse = $this->evaluator->satisfied($course, $user, $percentage);

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
