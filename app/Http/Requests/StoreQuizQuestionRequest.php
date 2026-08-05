<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SPEC-08 §1.2 — validates creation of a `QuizQuestion` + its nested
 * `quiz_options`. `options` is required for every `type` except `essay`
 * (RN11 — `quiz_options` does not apply to essay questions); when present,
 * exactly 1 correct option is required for `single_choice`/`true_false`,
 * and at least 1 for `multiple_choice`.
 *
 * Authorization is not delegated to a dedicated `QuizQuestionPolicy` —
 * question authoring is part of managing the parent `Quiz`, so this
 * reuses `QuizPolicy::update()` against the route-bound `{quiz}`.
 */
class StoreQuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('quiz'));
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
            'options.*.option_text' => ['required_with:options', 'string', 'max:500'],
            'options.*.is_correct' => ['boolean'],
        ];
    }

    /**
     * Cross-field RN11/§1.2 rules that `rules()` alone cannot express:
     * exactly 1 correct option for `single_choice`/`true_false`, at least
     * 1 for `multiple_choice`.
     */
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
