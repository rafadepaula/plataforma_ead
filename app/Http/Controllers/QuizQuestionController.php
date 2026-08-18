<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizQuestionRequest;
use App\Http\Requests\UpdateQuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * nested question+option CRUD + reorder, scoped to a Quiz.
 * There is no dedicated `quiz-questions/create|edit` full-page screen —
 * per `quizzes/edit.blade.php`'s contract (Bucket 3), Questions are
 * authored via modals on the parent Quiz's single edit screen, so only
 * `store`/`update`/`destroy`/`reorder` are routed (see `routes/web.php`).
 * Authorization is not delegated to a dedicated `QuizQuestionPolicy` —
 * question authoring is part of managing the parent `Quiz`, so every
 * action reuses `QuizPolicy::update()`.
 */
class QuizQuestionController extends Controller
{
    public function store(StoreQuizQuestionRequest $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validated();
        $options = $data['options'] ?? [];
        unset($data['options']);
        $data['order_index'] = $data['order_index'] ?? $quiz->questions()->count();

        DB::transaction(function () use ($quiz, $data, $options): void {
            $question = $quiz->questions()->create($data);

            foreach ($options as $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ]);
            }
        });

        return redirect()->route('quizzes.edit', $quiz)
            ->with('success', 'Questão criada com sucesso.');
    }

    public function update(UpdateQuizQuestionRequest $request, QuizQuestion $quizQuestion): RedirectResponse
    {
        $data = $request->validated();
        $options = $data['options'] ?? [];
        unset($data['options']);

        DB::transaction(function () use ($quizQuestion, $data, $options): void {
            $quizQuestion->update($data);

            $keptOptionIds = [];

            foreach ($options as $option) {
                if (! empty($option['id'])) {
                    $quizQuestion->options()->where('id', $option['id'])->update([
                        'option_text' => $option['option_text'],
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                    ]);
                    $keptOptionIds[] = (int) $option['id'];

                    continue;
                }

                $created = $quizQuestion->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ]);
                $keptOptionIds[] = $created->id;
            }

            if ($quizQuestion->type !== 'essay') {
                $quizQuestion->options()->whereNotIn('id', $keptOptionIds)->delete();
            } else {
                $quizQuestion->options()->delete();
            }
        });

        return redirect()->route('quizzes.edit', $quizQuestion->quiz)
            ->with('success', 'Questão atualizada com sucesso.');
    }

    public function destroy(QuizQuestion $quizQuestion): RedirectResponse
    {
        Gate::authorize('update', $quizQuestion->quiz);

        $quiz = $quizQuestion->quiz;
        $quizQuestion->delete();

        return redirect()->route('quizzes.edit', $quiz)
            ->with('success', 'Questão removida com sucesso.');
    }

    /**
     * AJAX reorder endpoint, scoped to a Quiz. Same defense-in-depth and
     * dense-reassignment approach as `ModuleController::reorder()`/
     * `LessonController::reorder()`.
     */
    public function reorder(Request $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', Rule::exists('quiz_questions', 'id')],
        ]);

        $orderedIds = $data['ordered_ids'];

        $questions = $quiz->questions()->whereIn('id', $orderedIds)->get()->keyBy('id');

        if ($questions->count() !== count($orderedIds)) {
            return response()->json([
                'message' => 'Uma ou mais questões não pertencem a este questionário.',
            ], 422);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $questions->get($id)->update(['order_index' => $index]);
        }

        return response()->json(['message' => 'Ordem das questões atualizada com sucesso.']);
    }
}
