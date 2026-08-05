<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SPEC-08 §2 — the quiz correction engine. A single call corrects every
 * auto-gradable question (`single_choice`/`multiple_choice`/`true_false`)
 * and records any `essay` answer for later manual grading (RN11).
 *
 * The student-facing UI is a single-page form (all questions POSTed at
 * once, see the `quizzes-architecture` skill) — there is no separate
 * "start attempt" step, so this action both creates and immediately
 * corrects the `QuizAttempt` in one pass.
 */
class SubmitQuizAttemptAction
{
    public function __construct(protected MarkLessonCompleteAction $markLessonCompleteAction) {}

    /**
     * @param  array<int, array{question_id: int, selected_option_ids?: array<int, int>|null, essay_answer?: string|null}>  $answers
     */
    public function execute(Lesson $lesson, User $user, array $answers): QuizAttempt
    {
        /** @var Quiz $quiz */
        $quiz = $lesson->quiz()->firstOrFail();

        $this->guardActiveEnrollment($lesson, $user);
        $this->guardAttemptLimits($quiz, $user);

        $startedAt = now();

        $attempt = QuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
            'started_at' => $startedAt,
        ]);

        $answersByQuestionId = collect($answers)->keyBy('question_id');

        $questions = $quiz->questions()->with('options')->orderBy('order_index')->get();

        $hasPendingEssay = false;
        $correctCount = 0;

        foreach ($questions as $question) {
            $answerInput = $answersByQuestionId->get($question->id, []);

            if ($question->type === 'essay') {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'essay_answer' => $answerInput['essay_answer'] ?? null,
                    'is_correct' => null,
                ]);
                $hasPendingEssay = true;

                continue;
            }

            $selectedOptionIds = array_values(array_map('intval', $answerInput['selected_option_ids'] ?? []));
            $isCorrect = $this->isObjectiveAnswerCorrect($question, $selectedOptionIds);

            $attempt->answers()->create([
                'question_id' => $question->id,
                'selected_option_ids' => $selectedOptionIds,
                'is_correct' => $isCorrect,
            ]);

            if ($isCorrect) {
                $correctCount++;
            }
        }

        if ($hasPendingEssay) {
            $attempt->update([
                'status' => 'awaiting_manual_grading',
                'score_percentage' => null,
                'is_passed' => null,
                'completed_at' => now(),
            ]);

            return $attempt->fresh(['answers']);
        }

        $this->finalize($attempt, $quiz, $lesson, $user, $correctCount, $questions->count());

        return $attempt->fresh(['answers']);
    }

    /**
     * SPEC-08 §2.1 — recomputes `score_percentage` over every question
     * (auto-graded + manually-graded essay) once every essay answer of an
     * `awaiting_manual_grading` attempt has been graded, then finalizes
     * the same completion flow §2 step 5 describes.
     */
    public function finalizeGrading(QuizAttempt $attempt): QuizAttempt
    {
        $quiz = $attempt->quiz;
        $lesson = $quiz->lesson;
        $user = $attempt->user;

        $totalQuestions = $quiz->questions()->count();
        $correctCount = $attempt->answers()->where('is_correct', true)->count();

        $this->finalize($attempt, $quiz, $lesson, $user, $correctCount, $totalQuestions);

        return $attempt->fresh(['answers']);
    }

    /**
     * Shared by both the no-essay auto-grade path and
     * {@see finalizeGrading()}: computes the percentage score, applies
     * the §1.3 time-limit rule, persists `status = graded`, and marks the
     * Lesson complete when passed.
     */
    protected function finalize(
        QuizAttempt $attempt,
        Quiz $quiz,
        Lesson $lesson,
        User $user,
        int $correctCount,
        int $totalQuestions,
    ): void {
        $scorePercentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $timeExceeded = $this->timeExceeded($quiz, $attempt);

        $isPassed = ! $timeExceeded && $scorePercentage >= $quiz->min_score_percentage;

        $attempt->update([
            'status' => 'graded',
            'score_percentage' => $scorePercentage,
            'is_passed' => $isPassed,
            'completed_at' => $attempt->completed_at ?? now(),
        ]);

        if ($isPassed) {
            $this->markLessonCompleteAction->execute($lesson, $user, 'quiz_passed');
        }
    }

    /**
     * SPEC-08 §1.3 — computed on read from `started_at`/`completed_at`/
     * `time_limit_minutes` rather than persisted as text (the schema has
     * no notes/warning column). An over-limit submission is still
     * accepted, only `is_passed` is forced to `false`.
     */
    protected function timeExceeded(Quiz $quiz, QuizAttempt $attempt): bool
    {
        if (! $quiz->time_limit_minutes || ! $attempt->completed_at) {
            return false;
        }

        return $attempt->started_at->diffInMinutes($attempt->completed_at) > $quiz->time_limit_minutes;
    }

    /**
     * SPEC-08 §1.2/RN02/RN03 — `single_choice`/`true_false` require an
     * exact 1-id match; `multiple_choice` requires the selected set to be
     * exactly the correct set (no partial credit). An empty
     * `selected_option_ids` is always incorrect, never a vacuous match.
     *
     * @param  list<int>  $selectedOptionIds
     */
    protected function isObjectiveAnswerCorrect(QuizQuestion $question, array $selectedOptionIds): bool
    {
        if (empty($selectedOptionIds)) {
            return false;
        }

        $correctOptionIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $selected = collect($selectedOptionIds)->sort()->values()->all();

        return $correctOptionIds === $selected;
    }

    /**
     * SPEC-08 §2 step 1 — the student.enrolled middleware (bucket 2) is
     * the primary HTTP-layer guard, but this Action re-verifies at the
     * business-rule layer too (defense in depth, and so it can be tested
     * directly without a full HTTP stack — mirrors
     * `ProcessSmartInvitationAction`'s convention of validating inside
     * the Action rather than trusting the caller).
     */
    protected function guardActiveEnrollment(Lesson $lesson, User $user): void
    {
        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();

        if (! $user->hasActiveOrCompletedEnrollment($course)) {
            throw ValidationException::withMessages([
                'quiz' => 'Você não possui matrícula ativa neste curso.',
            ]);
        }
    }

    /**
     * SPEC-08 §1.3 — counts only completed submissions (`status` in
     * `awaiting_manual_grading`/`graded`), never an abandoned
     * `in_progress` attempt.
     */
    protected function guardAttemptLimits(Quiz $quiz, User $user): void
    {
        $completedAttempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['awaiting_manual_grading', 'graded'])
            ->count();

        if (! $quiz->allow_retries && $completedAttempts >= 1) {
            throw ValidationException::withMessages([
                'quiz' => 'Este questionário não permite novas tentativas.',
            ]);
        }

        if ($quiz->max_attempts !== null && $completedAttempts >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'quiz' => 'Você atingiu o número máximo de tentativas para este questionário.',
            ]);
        }
    }
}
