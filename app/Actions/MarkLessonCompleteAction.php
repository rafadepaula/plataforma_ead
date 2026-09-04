<?php

namespace App\Actions;

use App\Events\LessonMarkedAsCompleted;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

/**
 * the single write path for `lesson_progress`, reused by
 * `LessonProgressController` (manual click / video threshold, bucket 2)
 * and, eventually, 's `SubmitQuizAttemptAction`.
 *
 * `is_completed` is idempotent (never unset once `true`); watched seconds
 * are persisted as the UNION of played intervals
 * (`LessonProgress::applyWatchedSegments()`) whenever segments are passed
 * (video lessons only — manual completions call this with `null`), so a
 * forward seek can never inflate the figure and replay never double-counts;
 * `completed_at` is set on first completion only; `LessonMarkedAsCompleted`
 * is dispatched only on the `false` → `true` transition, never on a
 * repeat/idempotent call.
 */
class MarkLessonCompleteAction
{
    /**
     * @param  list<array<string, int>>|list<array{0: int, 1: int}>|null  $watchedSegments
     */
    public function execute(
        Lesson $lesson,
        User $user,
        string $completionSource,
        ?array $watchedSegments = null,
        ?int $durationSeconds = null,
        ?int $lastPositionSeconds = null,
    ): LessonProgress {
        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $wasCompleted = (bool) $progress->is_completed;

        if ($watchedSegments !== null) {
            $progress->applyWatchedSegments($watchedSegments, $durationSeconds);
        }

        if ($lastPositionSeconds !== null) {
            $progress->last_position_seconds = $lastPositionSeconds;
        }

        $progress->completion_source = $completionSource;
        $progress->is_completed = true;

        if (! $wasCompleted) {
            $progress->completed_at = now();
        }

        $progress->save();

        if (! $wasCompleted) {
            LessonMarkedAsCompleted::dispatch($lesson, $user);
        }

        return $progress;
    }
}
