<?php

namespace App\Http\Controllers;

use App\Actions\GradeEssayAnswerAction;
use App\Http\Requests\GradeEssayAnswerRequest;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * the Gestor's manual essay-grading screen: a pending
 * -attempts queue (`pending`) and the per-attempt grading action
 * (`grade`). `QuizAttempt` carries no `OrgScope` of its own (cascade
 * -inherited), so `pending()` relies on `Course`'s own `OrgScope` being
 * applied automatically inside the `whereHas` existence subquery — the
 * same implicit-scoping pattern `CourseController::index()` uses — while
 * `show()`/`grade()` are guarded explicitly by `QuizAttemptPolicy`.
 */
class EssayGradingController extends Controller
{
    public function __construct(protected GradeEssayAnswerAction $gradeEssayAnswerAction) {}

    public function pending(): View
    {
        $attempts = QuizAttempt::query()
            ->with(['quiz.lesson.module.course', 'user'])
            ->where('status', 'awaiting_manual_grading')
            ->whereHas('quiz.lesson.module.course')
            ->orderBy('completed_at')
            ->paginate(15);

        return view('quizzes.attempts.pending', ['attempts' => $attempts]);
    }

    public function show(QuizAttempt $quizAttempt): View
    {
        Gate::authorize('view', $quizAttempt);

        $quizAttempt->load(['quiz.lesson.module.course', 'user', 'answers.question']);

        return view('quizzes.attempts.show', ['attempt' => $quizAttempt]);
    }

    public function grade(GradeEssayAnswerRequest $request, QuizAttempt $quizAttempt): RedirectResponse
    {
        $this->gradeEssayAnswerAction->execute(
            $quizAttempt,
            $request->user(),
            $request->validated('grades'),
        );

        return redirect()->route('quiz-attempts.pending')
            ->with('success', 'Correção registrada com sucesso.');
    }
}
