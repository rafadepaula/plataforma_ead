<?php

namespace App\Actions;

use App\Models\QuizAttempt;
use App\Models\User;

/**
 * SPEC-08 §2.1 — the Gestor's manual essay-grading write path. Grades 1+
 * pending `essay` `quiz_answers` of an `awaiting_manual_grading`
 * `QuizAttempt`, then delegates to
 * {@see SubmitQuizAttemptAction::finalizeGrading()} once every essay
 * answer of the attempt has a verdict.
 */
class GradeEssayAnswerAction
{
    public function __construct(protected SubmitQuizAttemptAction $submitQuizAttemptAction) {}

    /**
     * @param  array<int, array{answer_id: int, is_correct: bool}>  $grades
     */
    public function execute(QuizAttempt $attempt, User $gestor, array $grades): QuizAttempt
    {
        foreach ($grades as $grade) {
            $attempt->answers()
                ->where('id', $grade['answer_id'])
                ->whereNotNull('essay_answer')
                ->update([
                    'is_correct' => $grade['is_correct'],
                    'graded_by' => $gestor->id,
                    'graded_at' => now(),
                ]);
        }

        $stillPending = $attempt->answers()
            ->whereNotNull('essay_answer')
            ->whereNull('is_correct')
            ->exists();

        if (! $stillPending) {
            return $this->submitQuizAttemptAction->finalizeGrading($attempt->fresh());
        }

        return $attempt->fresh(['answers']);
    }
}
