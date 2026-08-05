<?php

namespace App\Listeners;

use App\Events\CourseCompletedByStudent;
use App\Events\LessonMarkedAsCompleted;

/**
 * SPEC-07 RF20 — auto-discovered (type-hinted `handle()` parameter).
 * Recomputes `course_user.progress_percentage` for the completing
 * student, scoped to published lessons whose module/course are not
 * soft-deleted, and — when a `rule_type = all_lessons`
 * `course_completion_rules` row's `required_percentage` is reached —
 * marks the enrollment `completed` and dispatches
 * `CourseCompletedByStudent`. Runs synchronously (`QUEUE_CONNECTION=sync`).
 */
class RecalculateCourseProgress
{
    public function handle(LessonMarkedAsCompleted $event): void
    {
        // Same cascade pattern as `LessonPolicy::parentCourse()` — bypass
        // `OrgScope` so this resolves regardless of the current
        // request/queue-worker's tenant context.
        $course = $event->lesson->module->course()->withoutGlobalScopes()->firstOrFail();

        $totalPublishedLessons = $course->publishedLessonsCountFor();
        $completedLessons = $course->completedLessonsCountFor($event->user);

        $percentage = $totalPublishedLessons > 0
            ? (int) round($completedLessons / $totalPublishedLessons * 100)
            : 0;

        $pivotUpdate = ['progress_percentage' => $percentage];

        $rule = $course->completionRules()
            ->where('rule_type', 'all_lessons')
            ->first();

        $shouldCompleteCourse = $rule && $percentage >= $rule->required_percentage;

        if ($shouldCompleteCourse) {
            $pivotUpdate['status'] = 'completed';
            $pivotUpdate['completed_at'] = now();
        }

        $course->students()->updateExistingPivot($event->user->id, $pivotUpdate);

        if ($shouldCompleteCourse) {
            CourseCompletedByStudent::dispatch($course, $event->user);
        }
    }
}
