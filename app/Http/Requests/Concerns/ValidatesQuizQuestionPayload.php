<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared rule set for the `questions[...]` authoring payload embedded in
 * the Lesson form when `type = quiz` (see `StoreLessonRequest`/
 * `UpdateLessonRequest`). Mirrors the per-question contract of
 * `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest` — question_text,
 * `type` (single_choice|multiple_choice|true_false|essay) and a nested
 * `options[]` sub-array with `option_text`/`is_correct` (and an optional
 * persisted `id` for upsert semantics, "delete what is no longer present"
 * for the rest):
 *   - `options` is skipped/ignored server-side for `type = essay` (no
 *     `quiz_options` row makes sense there) — the embedded builder hides
 *     the options UI client-side, but the server must not trust that.
 *   - Exactly 1 correct option is required for `single_choice`/`true_false`,
 *     at least 1 for `multiple_choice` (§1.2 cross-field rules, enforced in
 *     `withValidator()`-style `after` hooks via `validateCorrectness()`).
 */
trait ValidatesQuizQuestionPayload
{
    /**
     * Rules for every entry of the `questions[...]` payload array.
     *
     * @return array<string, mixed>
     */
    public function quizQuestionRules(): array
    {
        return [
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['nullable', 'integer', 'exists:quiz_questions,id'],
            'questions.*.question_text' => ['required_with:questions', 'string'],
            'questions.*.type' => [
                'required_with:questions',
                Rule::in(['single_choice', 'multiple_choice', 'true_false', 'essay']),
            ],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.id' => ['nullable', 'integer', 'exists:quiz_options,id'],
            'questions.*.options.*.option_text' => ['required_with:questions.*.options', 'string', 'max:500'],
            'questions.*.options.*.is_correct' => ['boolean'],
        ];
    }

    /**
     * Cross-field rules per question: exactly 1 correct option for
     * `single_choice`/`true_false`, at least 1 for `multiple_choice`.
     * Call from the FormRequest's `withValidator()` hook.
     */
    public function validateQuestionCorrectness(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('questions', []) as $index => $question) {
                if (! is_array($question) || ($question['type'] ?? null) === 'essay') {
                    continue;
                }

                $options = collect((array) ($question['options'] ?? []));
                $correctCount = $options->filter(fn ($option): bool => (bool) ($option['is_correct'] ?? false))->count();

                if (in_array($question['type'], ['single_choice', 'true_false'], true) && $correctCount !== 1) {
                    $validator->errors()->add(
                        'questions.'.$index.'.options',
                        'Questões de escolha única devem ter exatamente 1 opção correta.'
                    );
                }

                if ($question['type'] === 'multiple_choice' && $correctCount < 1) {
                    $validator->errors()->add(
                        'questions.'.$index.'.options',
                        'Questões de múltipla escolha devem ter ao menos 1 opção correta.'
                    );
                }
            }
        });
    }
}
