<?php

namespace App\Http\Requests;

use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC13  — validates creation of a `CourseCompletionRule` for
 * the route-bound `{course}`. `target_id` is a pseudo-polymorphic pointer
 * with no real DB foreign key (see `CourseCompletionRule`'s docblock), so
 * this Request is the only place its integrity is enforced: required for
 * `min_quiz_score`/`specific_module`, prohibited for `all_lessons`, and —
 * since the column has no FK to lean on — must resolve to a
 * `quizzes.id`/`modules.id` that actually belongs to `$course`, never a
 * cross-course/cross-tenant one. Cross-field rules live in `withValidator`
 * mirroring `StoreQuizQuestionRequest`'s convention.
 *
 * Authorization is not delegated to a dedicated Policy method — completion
 * rule authoring is part of managing the parent `Course`, so this reuses
 * `CoursePolicy::update()` against the route-bound `{course}`, matching
 * `StoreEnrollmentRequest`'s convention.
 */
class StoreCourseCompletionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAllLessons = $this->input('rule_type') === 'all_lessons';

        return [
            'rule_type' => ['required', Rule::in(['all_lessons', 'min_quiz_score', 'specific_module'])],
            'required_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'target_id' => [$isAllLessons ? 'prohibited' : 'required', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_id.required' => 'Selecione o alvo (quiz ou módulo) para este tipo de regra.',
            'target_id.prohibited' => 'A regra "Todas as Lições" não deve informar um alvo.',
        ];
    }

    /**
     * `target_id` must resolve to a `quizzes.id`/`modules.id` that actually
     * belongs to the route-bound `{course}` — the DB has no FK to enforce
     * this (see `CourseCompletionRule`'s docblock), so it is checked here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('rule_type');
            $targetId = $this->input('target_id');
            $course = $this->route('course');

            if (! $targetId || ! $course || $validator->errors()->has('target_id')) {
                return;
            }

            if ($type === 'min_quiz_score') {
                $belongsToCourse = Quiz::query()
                    ->whereKey($targetId)
                    ->whereHas('lesson.module', fn ($query) => $query->where('course_id', $course->id))
                    ->exists();

                if (! $belongsToCourse) {
                    $validator->errors()->add('target_id', 'Este quiz não pertence a este curso.');
                }
            }

            if ($type === 'specific_module') {
                $belongsToCourse = Module::query()
                    ->whereKey($targetId)
                    ->where('course_id', $course->id)
                    ->exists();

                if (! $belongsToCourse) {
                    $validator->errors()->add('target_id', 'Este módulo não pertence a este curso.');
                }
            }
        });
    }
}
