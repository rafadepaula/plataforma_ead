<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Persists the quiz authoring payload embedded in the Lesson form (the
 * lesson form is THE surface for quiz authoring — see
 * `modules/lessons/_form.blade.php`), so `LessonController::store()`/
 * `update()` and the standalone `QuizController` share one persistence
 * path instead of duplicating transactional question/option logic.
 *
 * Contract of the payload (validated by `StoreLessonRequest`/
 * `UpdateLessonRequest` via `ValidatesQuizQuestionPayload`):
 *   - `$quizData`  the `quiz[...]` meta array. The quiz `title` is NOT
 *     part of it — it is always re-synced to the Lesson `title`, because
 *     the lesson form has a single title field and keeping two editable
 *     titles for the same screen would be confusing (decision recorded in
 *     `modules/lessons/_form.blade.php` too).
 *   - `$questions` the `questions[...]` builder array, in display order
 *     (payload index becomes `order_index`). Each entry upserts by
 *     `id` (scoped to THIS quiz — foreign ids are ignored and treated as
 *     new rows, never a cross-tenant write) and carries a nested
 *     `options[]` array with the same upsert/delete-missing semantics as
 *     `QuizQuestionController::update()`. A `null` payload means "do not
 *     touch the persisted questions" (quiz meta may still sync); an
 *     empty array is a full sync to zero questions. `essay` questions
 *     always end up with no options.
 */
class SaveQuizForLessonAction
{
    /**
     * @param  array<string, mixed>|null  $quizData  null => do nothing.
     * @param  array<int, array<string, mixed>>|null  $questions  null => keep questions as-is.
     */
    public function handle(Lesson $lesson, ?array $quizData, ?array $questions = null): ?Quiz
    {
        if ($quizData === null) {
            return null;
        }

        return DB::transaction(function () use ($lesson, $quizData, $questions): Quiz {
            $quiz = $lesson->quiz()->firstOrCreate([], [
                ...$quizData,
                'title' => $lesson->title,
            ]);

            $quiz->update([...$quizData, 'title' => $lesson->title]);

            if ($questions !== null) {
                $this->syncQuestions($quiz, $questions);
            }

            return $quiz->refresh();
        });
    }

    /**
     * Full sync: payload order becomes `order_index`, entries with a
     * persisted (own) `id` are updated in place, the rest created, and
     * every persisted question absent from the payload is deleted (its
     * options/options gone via the `ON DELETE CASCADE` FKs).
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function syncQuestions(Quiz $quiz, array $questions): void
    {
        $ownQuestionIds = $quiz->questions()->pluck('id');
        $keptQuestionIds = [];

        foreach (array_values($questions) as $index => $question) {
            $type = $question['type'];
            $options = $type === 'essay' ? [] : array_values((array) ($question['options'] ?? []));

            $questionId = $question['id'] ?? null;
            if ($questionId !== null && $ownQuestionIds->contains((int) $questionId)) {
                $persisted = $quiz->questions()->where('id', $questionId)->firstOrFail();
                $persisted->update([
                    'question_text' => $question['question_text'],
                    'type' => $type,
                    'order_index' => $index,
                ]);
            } else {
                $persisted = $quiz->questions()->create([
                    'question_text' => $question['question_text'],
                    'type' => $type,
                    'order_index' => $index,
                ]);
            }

            $this->syncOptions($persisted, $options);
            $keptQuestionIds[] = $persisted->id;
        }

        $quiz->questions()->whereNotIn('id', $keptQuestionIds ?: [0])->delete();
    }

    /**
     * Upsert by `id` (scoped to the question), delete whatever persisted
     * option is no longer present — identical semantics to
     * `QuizQuestionController::update()`. Essay questions always end up
     * with zero options.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(QuizQuestion $question, array $options): void
    {
        $ownOptionIds = $question->options()->pluck('id');
        $keptOptionIds = [];

        foreach ($options as $option) {
            $attributes = [
                'option_text' => $option['option_text'],
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ];

            $optionId = $option['id'] ?? null;
            if ($optionId !== null && $ownOptionIds->contains((int) $optionId)) {
                $question->options()->where('id', $optionId)->update($attributes);
                $keptOptionIds[] = (int) $optionId;

                continue;
            }

            $keptOptionIds[] = $question->options()->create($attributes)->id;
        }

        $question->options()->whereNotIn('id', $keptOptionIds ?: [0])->delete();
    }
}
