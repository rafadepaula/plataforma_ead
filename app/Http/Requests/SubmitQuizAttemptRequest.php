<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating the student's single-page quiz submission
 * (`POST /lessons/{lesson}/quiz/submit`). `answers` is keyed by `question_id`;
 * `selected_option_ids` is used for objective questions, `essay_answer`
 * for essay questions.
 *
 * Every question of the quiz is mandatory: objective questions require at
 * least one selected option, essay questions require non-blank text — so an
 * attempt can never be finalized with unanswered questions, even when the
 * request bypasses the client-side button lock.
 *
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
        $rules = [
            'answers' => ['present', 'array'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'answers.*.selected_option_ids.*' => ['integer', 'exists:quiz_options,id'],
            'answers.*.essay_answer' => ['nullable', 'string'],
        ];

        $questions = $this->route('lesson')?->quiz?->questions ?? collect();

        foreach ($questions as $question) {
            if ($question->type === 'essay') {
                $rules["answers.{$question->id}.essay_answer"] = [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) === '') {
                            $fail('A resposta dissertativa não pode ficar em branco.');
                        }
                    },
                ];

                continue;
            }

            $rules["answers.{$question->id}.selected_option_ids"] = ['required', 'array', 'min:1'];
        }

        return $rules;
    }
}
