<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates updates to an existing `QuizQuestion` + its
 * nested `quiz_options`. Same rule shape as {@see StoreQuizQuestionRequest};
 * authorization reuses `QuizPolicy::update()` against the question's
 * parent `Quiz`.
 */
class UpdateQuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('quiz_question')?->quiz);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isEssay = $this->input('type') === 'essay';

        return [
            'question_text' => ['required', 'string'],
            'type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'essay'])],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'options' => [$isEssay ? 'prohibited' : 'required', 'array', 'min:2'],
            'options.*.id' => ['nullable', 'integer', 'exists:quiz_options,id'],
            'options.*.option_text' => ['required_with:options', 'string', 'max:500'],
            'options.*.is_correct' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');

            if ($type === 'essay') {
                return;
            }

            $options = collect($this->input('options', []));
            $correctCount = $options->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->count();

            if (in_array($type, ['single_choice', 'true_false'], true) && $correctCount !== 1) {
                $validator->errors()->add('options', 'Questões de escolha única devem ter exatamente 1 opção correta.');
            }

            if ($type === 'multiple_choice' && $correctCount < 1) {
                $validator->errors()->add('options', 'Questões de múltipla escolha devem ter ao menos 1 opção correta.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if (($data['type'] ?? null) === 'essay') {
            unset($data['options']);
        }

        return $data;
    }
}
