<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\CourseCompletionEvaluator;
use Illuminate\Support\Facades\DB;

/**
 * Administrative reset of EVERY student's progress on a single video
 * Lesson — invoked by `LessonController::update()` only when the Gestor
 * ticked "Resetar progresso dos alunos neste vídeo" AND the sanitized
 * `video_url` actually changed. Progress rows are cleared IN PLACE
 * (columns zeroed, row preserved — a `user_id`/`lesson_id` row is identity,
 * not state), so re-watching starts from a clean union.
 *
 * The completion pipeline (`LessonMarkedAsCompleted` → `RecalculateCourseProgress`)
 * has no reverse event, so the `course_user` pivot is recomputed here
 * directly: the fresh percentage is persisted for every affected student and
 * a `completed` enrollment whose completion no longer holds is reverted to
 * `active` (via `CourseCompletionEvaluator`, so ANY registered rule — not
 * just `all_lessons` — is honored). `certificates` are intentionally NOT
 * revoked (revocation is a separate, explicit administrative act).
 *
 * @return int the number of `lesson_progress` rows cleared (0 = no-op)
 */
class ResetLessonVideoProgressAction
{
    public function __construct(
        protected CourseCompletionEvaluator $evaluator = new CourseCompletionEvaluator,
    ) {}

    public function execute(Lesson $lesson): int
    {
        return DB::transaction(function () use ($lesson): int {
            $cleared = 0;
            $affectedUserIds = [];

            LessonProgress::query()
                ->where('lesson_id', $lesson->id)
                ->chunkById(500, function ($rows) use (&$cleared, &$affectedUserIds): void {
                    foreach ($rows as $progress) {
                        $progress->forceFill([
                            'watched_ranges' => null,
                            'watched_unique_seconds' => 0,
                            'duration_seconds' => 0,
                            'last_position_seconds' => 0,
                            'is_completed' => false,
                            'completion_source' => null,
                            'completed_at' => null,
                        ])->saveQuietly();

                        $affectedUserIds[] = $progress->user_id;
                        $cleared++;
                    }
                });

            foreach (array_unique($affectedUserIds) as $userId) {
                $this->recomputeEnrollment($lesson, $userId);
            }

            return $cleared;
        });
    }

    /**
     * Persists the student's fresh `progress_percentage` and reverts a
     * `completed` pivot to `active` when the course no longer satisfies its
     * completion rules after the reset — mirroring the write shape of
     * `EvaluateCourseCompletionAction` without its fire-only-forward
     * transition discipline.
     */
    private function recomputeEnrollment(Lesson $lesson, int $userId): void
    {
        $course = $lesson->module->course;
        /** @var User|null $user */
        $user = User::find($userId);

        if ($user === null) {
            return;
        }

        $pivot = $course->students()->where('user_id', $userId)->first()?->pivot;

        if ($pivot === null) {
            return;
        }

        $totalPublishedLessons = $course->publishedLessonsCountFor();
        $completedLessons = $course->completedLessonsCountFor($user);

        $percentage = $totalPublishedLessons > 0
            ? (int) round($completedLessons / $totalPublishedLessons * 100)
            : 0;

        $pivotUpdate = ['progress_percentage' => $percentage];

        if (! $this->evaluator->satisfied($course, $user, $percentage) && $pivot->status === 'completed') {
            $pivotUpdate['status'] = 'active';
            $pivotUpdate['completed_at'] = null;
        }

        $course->students()->updateExistingPivot($userId, $pivotUpdate);
    }
}
