<?php

namespace App\Http\Controllers;

use App\Actions\MarkLessonCompleteAction;
use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPEC-07 RF20 — the two write endpoints behind `student.enrolled`
 * (see `routes/web.php`): manual "Marcar como concluída" clicks
 * (text/PDF/image lessons only) and the video player's 5s progress poll
 * (video lessons only). Both delegate the actual write to
 * `MarkLessonCompleteAction`, the single write path for `lesson_progress`
 * shared with the future SPEC-08 `SubmitQuizAttemptAction`.
 */
class LessonProgressController extends Controller
{
    public function __construct(protected MarkLessonCompleteAction $markLessonCompleteAction) {}

    /**
     * POST /lessons/{lesson}/complete — manual completion is only valid
     * for text/PDF/image lessons: a `type=quiz` lesson (reserved for
     * SPEC-08's `SubmitQuizAttemptAction`) or a lesson carrying a
     * `youtube_url` (which must reach completion via the 90%
     * video-threshold endpoint instead) is rejected with a 422.
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        // SPEC-07 line 32 — an unpublished/draft Lesson does not exist
        // from the Aluno's perspective (mirrors `ClassroomController`'s
        // `is_published` filter); Admin/Gestor retain preview access.
        if (! $lesson->is_published && $request->user()->hasRole(RolesEnum::ALUNO->value)) {
            abort(404);
        }

        if ($lesson->type === 'quiz' || ! empty($lesson->youtube_url)) {
            return response()->json([
                'message' => 'Esta lição não pode ser concluída manualmente.',
            ], 422);
        }

        $progress = $this->markLessonCompleteAction->execute($lesson, $request->user(), 'manual_click');

        return response()->json([
            'message' => 'Lição concluída com sucesso.',
            'is_completed' => $progress->is_completed,
        ]);
    }

    /**
     * POST /lessons/{lesson}/progress — the AJAX polling target hit every
     * 5s by `LessonPlayer.js` while a video lesson plays. Only valid for
     * video lessons: a `type=quiz` lesson (checked first, since quiz
     * completion is reserved for SPEC-08's `SubmitQuizAttemptAction` even
     * if malformed data also carries a `youtube_url`) or a lesson with an
     * empty `youtube_url` is rejected with a 422. Below the 90%
     * threshold, persists `watched_seconds` (GREATEST) without
     * completing; at/above it, delegates to `MarkLessonCompleteAction`
     * with `completion_source=video_threshold`.
     */
    public function updateProgress(UpdateLessonProgressRequest $request, Lesson $lesson): JsonResponse
    {
        // SPEC-07 line 32 — an unpublished/draft Lesson does not exist
        // from the Aluno's perspective (mirrors `ClassroomController`'s
        // `is_published` filter); Admin/Gestor retain preview access.
        if (! $lesson->is_published && $request->user()->hasRole(RolesEnum::ALUNO->value)) {
            abort(404);
        }

        if ($lesson->type === 'quiz' || empty($lesson->youtube_url)) {
            return response()->json([
                'message' => 'Esta lição não é um vídeo.',
            ], 422);
        }

        $watchedSeconds = (int) $request->validated('watched_seconds');
        $durationSeconds = (int) $request->validated('duration_seconds');
        $user = $request->user();

        $reachedThreshold = ($watchedSeconds / $durationSeconds) >= 0.90;

        if ($reachedThreshold) {
            $progress = $this->markLessonCompleteAction->execute($lesson, $user, 'video_threshold', $watchedSeconds);
        } else {
            $progress = LessonProgress::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);
            $progress->watched_seconds = max($watchedSeconds, (int) ($progress->watched_seconds ?? 0));
            $progress->save();
        }

        return response()->json([
            'watched_seconds' => $progress->watched_seconds,
            'is_completed' => $progress->is_completed,
        ]);
    }
}
