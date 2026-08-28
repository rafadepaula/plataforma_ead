<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating the student's single-page quiz submission
 * (`POST /lessons/{lesson}/quiz/submit`). `answers` is keyed by `question_id`;
 * `selected_option_ids` is used for objective questions, `essay_answer`
 * for essay questions — both are optional per-question.

 * The attempt's start time is never accepted from the client: it is
 * stamped server-side on the `in_progress` QuizAttempt when the quiz page
 * is opened.
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
