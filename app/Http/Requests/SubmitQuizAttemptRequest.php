<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-08 §2 — payload for the student's single-page quiz submission
 * (`POST /lessons/{lesson}/quiz`). `answers` is keyed by `question_id`;
 * `selected_option_ids` is used for `single_choice`/`multiple_choice`/
 * `true_false` questions, `essay_answer` for `type=essay` ones — both are
 * optional per-question (an unanswered question is scored as incorrect,
 * never excluded, per SPEC-08's edge cases).
 */
class SubmitQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'answers.*.selected_option_ids.*' => ['integer', 'exists:quiz_options,id'],
            'answers.*.essay_answer' => ['nullable', 'string'],
        ];
    }
}
