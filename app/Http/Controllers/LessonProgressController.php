<?php

namespace App\Http\Controllers;

use App\Actions\MarkLessonCompleteAction;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\VideoWatchCalculator;
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
     * A lesson whose `video_url` cannot be resolved into a video id has no
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

        if ($lesson->type === 'quiz' || $lesson->hasPlayableVideo()) {
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
     * 5s by the lesson player while a video lesson plays. Only valid for
     * video lessons: a `type=quiz` lesson (checked first, since quiz
     * completion is reserved for 's `SubmitQuizAttemptAction` even
     * if malformed data also carries a `video_url`) or a lesson without a
     * resolvable video id is rejected with a 422.
     *
     * The client reports raw played segments (`[{start, end}]`), never a
     * percentage: they are unioned into `lesson_progress.watched_ranges`
     * and the 90% threshold reads `watched_unique_seconds` — so a forward
     * seek cannot inflate progress and replay cannot double-count. Below
     * the threshold the row persists without completing (the one write that
     * bypasses `MarkLessonCompleteAction`, per the learning conventions);
     * at/above it the action completes with `completion_source=video_threshold`.
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

        if ($lesson->type === 'quiz' || ! $lesson->hasPlayableVideo()) {
            return response()->json([
                'message' => 'Esta lição não é um vídeo.',
            ], 422);
        }

        $data = $request->validated();
        $durationSeconds = (int) $data['duration_seconds'];
        $lastPositionSeconds = isset($data['position_seconds']) ? (int) $data['position_seconds'] : null;
        $user = $request->user();

        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);
        $uniqueSeconds = $progress->applyWatchedSegments($data['segments'], $durationSeconds);

        if (VideoWatchCalculator::reachedCompletion($uniqueSeconds, $durationSeconds)) {
            // The union is idempotent, so the action re-merging the same
            // batch against the persisted row reproduces exactly the
            // `$uniqueSeconds` computed above — threshold and stored state
            // can never disagree.
            $progress = $this->markLessonCompleteAction->execute(
                $lesson,
                $user,
                'video_threshold',
                $data['segments'],
                $durationSeconds,
                $lastPositionSeconds,
            );
        } else {
            // Latest playhead wins (not GREATEST): a backward seek must be
            // able to move the resume bookmark back.
            if ($lastPositionSeconds !== null) {
                $progress->last_position_seconds = $lastPositionSeconds;
            }
            $progress->save();
        }

        // The watched ranges travel back so the client can repaint the seek
        // bar's watched overlay and the watched-% indicator from the
        // server's authority instead of mirroring its own accounting.
        return response()->json([
            'watched_unique_seconds' => $progress->watched_unique_seconds,
            'watched_ranges' => $progress->watched_ranges ?? [],
            'duration_seconds' => $progress->duration_seconds,
            'last_position_seconds' => $progress->last_position_seconds,
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
