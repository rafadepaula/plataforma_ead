<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-08 §2.1 — payload for the Gestor's manual essay-grading action
 * (`POST /quiz-attempts/{quizAttempt}/grade`). `grades` is keyed by
 * `answer_id` (a `quiz_answers.id` belonging to the route-bound attempt),
 * value is the Gestor's `is_correct` verdict for that essay answer.
 */
class GradeEssayAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grade', $this->route('quizAttempt'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.answer_id' => ['required', 'integer', 'exists:quiz_answers,id'],
            'grades.*.is_correct' => ['required', 'boolean'],
        ];
    }
}
