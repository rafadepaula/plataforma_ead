<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * SPEC-08 RF08 — Gestor CRUD of the 1:1 Lesson<->Quiz, reserved to
 * `role:admin|gestor` (see `routes/web.php` and `QuizPolicy`).
 * `quizzes.lesson_id` is UNIQUE at the schema level — `create()`/`store()`
 * both guard against a Lesson that already has a Quiz with a redirect
 * -with-error rather than letting the DB throw a constraint violation.
 */
class QuizController extends Controller
{
    public function create(Lesson $lesson): View|RedirectResponse
    {
        Gate::authorize('create', [Quiz::class, $lesson]);

        if ($lesson->quiz()->exists()) {
            return redirect()->route('quizzes.edit', $lesson->quiz)
                ->with('error', 'Esta lição já possui um questionário.');
        }

        return view('quizzes.create', ['lesson' => $lesson, 'quiz' => new Quiz]);
    }

    public function store(StoreQuizRequest $request, Lesson $lesson): RedirectResponse
    {
        if ($lesson->quiz()->exists()) {
            return redirect()->route('quizzes.edit', $lesson->quiz)
                ->with('error', 'Esta lição já possui um questionário.');
        }

        $quiz = $lesson->quiz()->create($request->validated());

        return redirect()->route('quizzes.edit', $quiz)
            ->with('success', 'Questionário criado com sucesso. Adicione as questões abaixo.');
    }

    public function edit(Quiz $quiz): View
    {
        Gate::authorize('update', $quiz);

        $questions = $quiz->questions()->with('options')->orderBy('order_index')->get();

        return view('quizzes.edit', ['lesson' => $quiz->lesson, 'quiz' => $quiz, 'questions' => $questions]);
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $quiz->update($request->validated());

        return redirect()->route('quizzes.edit', $quiz)
            ->with('success', 'Questionário atualizado com sucesso.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        Gate::authorize('delete', $quiz);

        $lesson = $quiz->lesson;
        $quiz->delete();

        return redirect()->route('modules.lessons.index', $lesson->module)
            ->with('success', 'Questionário removido com sucesso.');
    }
}
