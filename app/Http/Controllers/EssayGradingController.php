<?php

namespace App\Http\Controllers;

use App\Actions\GradeEssayAnswerAction;
use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\GradeEssayAnswerRequest;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * the manual essay-grading screen: a pending
 * -attempts queue (`pending`) and the per-attempt grading action
 * (`grade`). `QuizAttempt` carries no `OrgScope` of its own (cascade
 * -inherited), so `pending()` relies on `Course`'s own `OrgScope` being
 * applied automatically inside the `whereHas` existence subquery — the
 * same implicit-scoping pattern `CourseController::index()` uses — while
 * `show()`/`grade()` are guarded explicitly by `QuizAttemptPolicy`.
 *
 * For a Professor the queue is further restricted to the Courses
 * assigned to them (`course_professor` pivot via `User::teaches()` /
 * `taughtCourses()`), FIFO unchanged — Admin/Gestor keep seeing the whole
 * (context-resolved) queue as before.
 */
class EssayGradingController extends Controller
{
    public function __construct(protected GradeEssayAnswerAction $gradeEssayAnswerAction) {}

    public function pending(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $attempts = QuizAttempt::query()
            ->with(['quiz.lesson.module.course', 'user'])
            ->where('status', 'awaiting_manual_grading')
            ->whereHas('quiz.lesson.module.course')
            ->when(
                $user->hasRole(RolesEnum::PROFESSOR->value),
                fn (Builder $query): Builder => $query->whereHas(
                    'quiz.lesson.module.course',
                    // `pluck` (e não `select`) — passar a relação para
                    // `whereKey` gera um sub-select com um objeto
                    // `BelongsToMany` como valor e explode com TypeError
                    // no MySQL (mesmo idiom de
                    // `ProfessorDashboardService::oldestPendingEssays()`).
                    fn (Builder $courseQuery): Builder => $courseQuery->whereKey($user->taughtCourses()->pluck('courses.id')),
                ),
            )
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
