<?php

namespace App\Http\Controllers;

use App\Actions\SubmitQuizAttemptAction;
use App\Http\Requests\SubmitQuizAttemptRequest;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller for the student quiz-taking flow, behind `student.enrolled`
 * and nested under `{lesson}` so enrollment resolution works consistently.
 * The UI is a single-page form — `show()` renders all questions at once,
 * and `submit()` processes the attempt via `SubmitQuizAttemptAction`.
 */
class StudentQuizController extends Controller
{
    public function __construct(protected SubmitQuizAttemptAction $submitQuizAttemptAction) {}

    public function show(Lesson $lesson): View
    {
        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
        $lesson->module->setRelation('course', $course);

        $quiz = $lesson->quiz()->with(['questions' => function ($query): void {
            $query->orderBy('order_index')->with('options');
        }])->firstOrFail();

        $user = request()->user();

        $completedAttempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['awaiting_manual_grading', 'graded'])
            ->count();

        $canAttempt = ($quiz->allow_retries || $completedAttempts === 0)
            && ($quiz->max_attempts === null || $completedAttempts < $quiz->max_attempts);

        $bestScore = $user->bestQuizScoreFor($quiz);

        $pendingAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_manual_grading')
            ->latest('id')
            ->first();

        $latestAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $hasPendingGrading = $pendingAttempt !== null;

        $latestGradedAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'graded')
            ->with('answers')
            ->latest('id')
            ->first();

        $showAnswerKey = (bool) ($quiz->show_correct_answers && $latestGradedAttempt !== null);

        return view('student.quizzes.show', [
            'lesson' => $lesson,
            'course' => $course,
            'quiz' => $quiz,
            'canAttempt' => $canAttempt,
            'completedAttempts' => $completedAttempts,
            'bestScore' => $bestScore,
            'pendingAttempt' => $pendingAttempt,
            'hasPendingGrading' => $hasPendingGrading,
            'latestAttempt' => $latestAttempt,
            'showAnswerKey' => $showAnswerKey,
            'latestGradedAttempt' => $latestGradedAttempt,
        ]);
    }

    public function submit(SubmitQuizAttemptRequest $request, Lesson $lesson): RedirectResponse
    {
        $answers = collect($request->validated('answers'))
            ->map(fn (array $answer, string $questionId): array => $answer + ['question_id' => (int) $questionId])
            ->values()
            ->all();

        try {
            $attempt = $this->submitQuizAttemptAction->execute($lesson, $request->user(), $answers);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($attempt->status === 'awaiting_manual_grading') {
            return redirect()->route('classroom.lesson', $lesson)
                ->with('success', 'Prova enviada. As questões dissertativas aguardam correção manual.');
        }

        $message = $attempt->is_passed
            ? "Prova concluída com sucesso! Nota: {$attempt->score_percentage}%."
            : "Prova enviada. Nota: {$attempt->score_percentage}%. Você não atingiu a nota mínima.";

        return redirect()->route('classroom.lesson', $lesson)->with('success', $message);
    }
}
