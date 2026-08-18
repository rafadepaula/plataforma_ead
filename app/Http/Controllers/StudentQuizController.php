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
 * the Aluno's quiz-taking flow, behind `student.enrolled`
 * (see `routes/web.php`) and nested under `{lesson}` (not a bare
 * `{quiz}`) so `EnsureStudentIsEnrolled`'s existing Course-resolution
 * logic keeps working unmodified. The UI is a single-page form — `show()`
 * renders every question at once, `submit()` corrects the whole attempt
 * in a single `SubmitQuizAttemptAction` call (see the
 * `quizzes-architecture` skill).
 */
class StudentQuizController extends Controller
{
    public function __construct(protected SubmitQuizAttemptAction $submitQuizAttemptAction) {}

    public function show(Lesson $lesson): View
    {
        // Resolve the Lesson's Course bypassing `OrgScope` and set it on the
        // `module` relation explicitly — an Aluno carries no `org_id` of
        // their own, so a plain `$lesson->module->course` access would
        // silently return null under the scope (see `learning-conventions`).
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

        // RN04 — the answer key is only ever surfaced once the student has
        // a graded attempt to show it against, and only when the Gestor
        // opted into `show_correct_answers` for this Quiz (see
        // `quizzes-architecture`).
        $latestGradedAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'graded')
            ->with('answers')
            ->latest('id')
            ->first();

        $showAnswerKey = $quiz->show_correct_answers && $latestGradedAttempt !== null;

        return view('student.quizzes.show', [
            'lesson' => $lesson,
            'quiz' => $quiz,
            'canAttempt' => $canAttempt,
            'completedAttempts' => $completedAttempts,
            'bestScore' => $bestScore,
            'pendingAttempt' => $pendingAttempt,
            'showAnswerKey' => $showAnswerKey,
            'latestGradedAttempt' => $latestGradedAttempt,
        ]);
    }

    public function submit(SubmitQuizAttemptRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->quiz()->firstOrFail();

        // The single-page form (see `student.quizzes.show`) posts
        // `answers` keyed by `question_id` (no separate `question_id`
        // field per entry) — `SubmitQuizAttemptAction::execute()` expects
        // a plain list of `{question_id, ...}` entries, so the key is
        // folded back in as a field here.
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
