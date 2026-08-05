<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF07 — payload for the AJAX Lesson reorder endpoint, scoped to a
 * Module. Same shape as {@see ReorderModulesRequest}; `LessonController::
 * reorder()` is responsible for confirming every id in `ordered_ids`
 * actually belongs to the route-bound `{module}`.
 */
class ReorderLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('module'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:lessons,id'],
        ];
    }
}
