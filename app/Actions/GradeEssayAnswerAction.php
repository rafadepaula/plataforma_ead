<?php

namespace App\Actions;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AuditService;
use Throwable;

/**
 * the Gestor's manual essay-grading write path. Grades 1+
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
            $answer = $attempt->answers()
                ->where('id', $grade['answer_id'])
                ->whereNotNull('essay_answer')
                ->first();

            if (! $answer) {
                continue;
            }

            $oldGrade = $answer->is_correct;

            $answer->update([
                'is_correct' => $grade['is_correct'],
                'graded_by' => $gestor->id,
                'graded_at' => now(),
            ]);

            // one `essay.graded` event per graded question.
            try {
                AuditService::log(
                    event: 'essay.graded',
                    orgId: $gestor->org_id ? (int) $gestor->org_id : null,
                    userId: $gestor->id,
                    payload: [
                        'quiz_attempt_id' => $attempt->id,
                        'question_id' => $answer->question_id,
                        'old_grade' => $oldGrade,
                        'new_grade' => $grade['is_correct'],
                        'evaluator_id' => $gestor->id,
                    ],
                );
            } catch (Throwable $e) {
                report($e);
            }
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
