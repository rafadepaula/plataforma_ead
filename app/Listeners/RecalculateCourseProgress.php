<?php

namespace App\Listeners;

use App\Actions\EvaluateCourseCompletionAction;
use App\Events\LessonMarkedAsCompleted;

/**
 * auto-discovered (type-hinted `handle()` parameter). Delegates the
 * percentage recompute/completion dispatch to
 * `EvaluateCourseCompletionAction` — the same evaluation
 * `CourseCompletionRuleController::store` applies retroactively to
 * students who reached the threshold before a rule existed. Runs
 * synchronously (`QUEUE_CONNECTION=sync`).
 */
class RecalculateCourseProgress
{
    public function __construct(
        protected EvaluateCourseCompletionAction $evaluateCourseCompletionAction,
    ) {}

    public function handle(LessonMarkedAsCompleted $event): void
    {
        // Same cascade pattern as `LessonPolicy::parentCourse()` — bypass
        // `OrgScope` so this resolves regardless of the current
        // request/queue-worker's tenant context.
        $course = $event->lesson->module->course()->withoutGlobalScopes()->firstOrFail();

        $this->evaluateCourseCompletionAction->execute($course, $event->user);
    }
}
