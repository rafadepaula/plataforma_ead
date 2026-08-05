<?php

namespace App\Http\Requests;

use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-08 RF08 — validates creation of a Lesson's 1:1 Quiz. `lesson_id`
 * is intentionally absent: it is always resolved from the route-bound
 * `{lesson}` segment by `QuizController::store()`, never trusted from
 * request input (mirrors `StoreLessonRequest`'s `module_id` convention).
 */
class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', [Quiz::class, $this->route('lesson')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string'],
            'allow_retries' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'show_correct_answers' => ['boolean'],
            'min_score_percentage' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        $data['allow_retries'] = $this->boolean('allow_retries');
        $data['show_correct_answers'] = $this->boolean('show_correct_answers');

        return $data;
    }
}
