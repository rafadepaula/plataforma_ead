<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The quiz correction engine. A single call corrects every
 * auto-gradable question (single_choice, multiple_choice, true_false)
 * and records any essay answer for later manual grading.
 *
 * The student-facing UI is a single-page form — there is no separate
 * start attempt step, so this action both creates and immediately
 * corrects the QuizAttempt in one pass.
 */
class SubmitQuizAttemptAction
{
    public function __construct(
        protected MarkLessonCompleteAction $markLessonCompleteAction,
        protected OpenQuizAttemptAction $openQuizAttemptAction,
    ) {}

    /**
     * The attempt start is always resolved server-side: an `in_progress`
     * QuizAttempt stamped when the student opened the quiz page is resumed
     * when one exists, otherwise the submission time is used. The client
     * never supplies `started_at`, so the time limit cannot be reset by
     * reloading the page or tampering with the form.
     *
     * @param  array<int, array{question_id: int, selected_option_ids?: array<int, int>|null, essay_answer?: string|null}>  $answers
     */
    public function execute(Lesson $lesson, User $user, array $answers): QuizAttempt
    {
        /** @var Quiz $quiz */
        $quiz = $lesson->quiz()->firstOrFail();

        $this->guardActiveEnrollment($lesson, $user);
        $this->guardAttemptLimits($quiz, $user);

        $attempt = $this->openQuizAttemptAction->openOrResume($quiz, $user);

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
     * Recomputes score_percentage over every question
     * (auto-graded + manually-graded essay) once every essay answer of an
     * awaiting_manual_grading attempt has been graded, then finalizes
     * the completion flow.
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
     * Shared by both the no-essay auto-grade path and finalizeGrading:
     * computes the percentage score, applies the time-limit rule,
     * persists status = graded, and marks the Lesson complete when passed.
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

        $completedAt = $attempt->completed_at ?? now();

        $timeExceeded = $this->timeExceeded($quiz, $attempt, $completedAt);

        $isPassed = ! $timeExceeded && $scorePercentage >= $quiz->min_score_percentage;

        $attempt->update([
            'status' => 'graded',
            'score_percentage' => $scorePercentage,
            'is_passed' => $isPassed,
            'completed_at' => $completedAt,
        ]);

        if ($isPassed) {
            $this->markLessonCompleteAction->execute($lesson, $user, 'quiz_passed');
        }
    }

    /**
     * Computed on read from started_at, completed_at, and time_limit_minutes.
     * An over-limit submission is accepted, but is_passed is forced to false.
     */
    protected function timeExceeded(Quiz $quiz, QuizAttempt $attempt, ?CarbonInterface $completedAt = null): bool
    {
        $completedAt = $completedAt ?? $attempt->completed_at;

        if (! $quiz->time_limit_minutes || ! $completedAt || ! $attempt->started_at) {
            return false;
        }

        return $attempt->started_at->diffInMinutes($completedAt) > $quiz->time_limit_minutes;
    }

    /**
     * Checks if the selected options exactly match the correct options.
     * Single choice and true/false require an exact 1-id match.
     * Multiple choice requires the selected set to match the full correct set.
     * An empty selection is always incorrect.
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
     * Validates active or completed enrollment for the course.
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
     * Counts only completed submissions (awaiting_manual_grading or graded),
     * never an abandoned in_progress attempt.
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
