<?php

namespace App\Http\Controllers;

use App\Actions\MarkLessonCompleteAction;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * the two write endpoints behind `student.enrolled`
 * (see `routes/web.php`): manual "Marcar como concluída" clicks
 * (text/PDF/image lessons only) and the video player's 5s progress poll
 * (video lessons only). Both delegate the actual write to
 * `MarkLessonCompleteAction`, the single write path for `lesson_progress`
 * shared with the future  `SubmitQuizAttemptAction`.
 */
class LessonProgressController extends Controller
{
    public function __construct(protected MarkLessonCompleteAction $markLessonCompleteAction) {}

    /**
     * POST /lessons/{lesson}/complete — manual completion is only valid
     * for text/PDF/image lessons: a `type=quiz` lesson (reserved for
     * 's `SubmitQuizAttemptAction`) or a lesson carrying a PLAYABLE
     * video (which must reach completion via the 90% video-threshold
     * endpoint instead) is rejected with a 422.
     *
     * A lesson whose `youtube_url` cannot be resolved into a video id has no
     * player to drive the threshold, so it stays manually completable —
     * otherwise a single broken link would make the lesson, and with it the
     * whole course progress, impossible to finish.
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        // an unpublished/draft Lesson does not exist from the perspective of
        // anyone who could reach this endpoint (mirrors `ClassroomController`'s
        // `is_published` filter). No staff exemption here: `abortUnlessEnrolled()`
        // below already refuses every non-enrolled actor, so a preview never
        // reaches the write either way.
        abort_unless($lesson->is_published, 404);

        $this->abortUnlessEnrolled($request, $lesson, $request->user());

        if ($lesson->type === 'quiz' || $lesson->youtube_video_id !== null) {
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
     * completion is reserved for 's `SubmitQuizAttemptAction` even
     * if malformed data also carries a `youtube_url`) or a lesson without a
     * resolvable video id is rejected with a 422. Below the 90%
     * threshold, persists `watched_seconds` (GREATEST) without
     * completing; at/above it, delegates to `MarkLessonCompleteAction`
     * with `completion_source=video_threshold`.
     */
    public function updateProgress(UpdateLessonProgressRequest $request, Lesson $lesson): JsonResponse
    {
        // an unpublished/draft Lesson does not exist from the perspective of
        // anyone who could reach this endpoint (mirrors `ClassroomController`'s
        // `is_published` filter). No staff exemption here: `abortUnlessEnrolled()`
        // below already refuses every non-enrolled actor, so a preview never
        // reaches the write either way.
        abort_unless($lesson->is_published, 404);

        $this->abortUnlessEnrolled($request, $lesson, $request->user());

        if ($lesson->type === 'quiz' || $lesson->youtube_video_id === null) {
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

    /**
     * `lesson_progress` is a student-only record: it feeds
     * `RecalculateCourseProgress` and, through `CourseCompletedByStudent`,
     * `IssueCertificateAction`. `EnsureStudentIsEnrolled` lets Admin and
     * same-org Gestor reach these routes so they can preview a course, but a
     * preview must never write progress — otherwise staff would collect a
     * real Certificate for a Course they were never enrolled in.
     */
    protected function abortUnlessEnrolled(Request $request, Lesson $lesson, User $user): void
    {
        $course = $this->resolveCourse($request, $lesson);

        abort_unless($user->hasActiveOrCompletedEnrollment($course), 403);
    }

    /**
     * `EnsureStudentIsEnrolled` já resolveu (e deixou no request) o Course
     * desta rota; reaproveitá-lo evita repetir `module` + `course` a cada
     * batida do polling de 5s. O fallback cobre a chamada direta ao
     * controller, fora da pilha de middleware.
     */
    protected function resolveCourse(Request $request, Lesson $lesson): Course
    {
        $course = $request->attributes->get(EnsureStudentIsEnrolled::RESOLVED_COURSE_ATTRIBUTE);

        if ($course instanceof Course) {
            return $course;
        }

        /** @var Course */
        return $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
    }
}
