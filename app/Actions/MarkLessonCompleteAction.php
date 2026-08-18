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
 * `is_completed` is idempotent (never unset once `true`); `watched_seconds`
 * is persisted as `GREATEST(current, reported)` whenever a value is passed
 * (video lessons only — manual completions call this with `null`);
 * `completed_at` is set on first completion only; `LessonMarkedAsCompleted`
 * is dispatched only on the `false` → `true` transition, never on a
 * repeat/idempotent call.
 */
class MarkLessonCompleteAction
{
    public function execute(
        Lesson $lesson,
        User $user,
        string $completionSource,
        ?int $watchedSeconds = null,
    ): LessonProgress {
        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $wasCompleted = (bool) $progress->is_completed;

        if ($watchedSeconds !== null) {
            $progress->watched_seconds = max($watchedSeconds, (int) ($progress->watched_seconds ?? 0));
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
