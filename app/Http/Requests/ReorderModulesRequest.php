<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF06 — payload for the AJAX Module reorder endpoint. Only validates
 * shape/existence here; `ModuleController::reorder()` is responsible for
 * confirming every id in `ordered_ids` actually belongs to the route-bound
 * `{course}` (an existence check alone would let a Gestor reorder/leak
 * another org's rows by ID guessing).
 */
class ReorderModulesRequest extends FormRequest
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
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:modules,id'],
        ];
    }
}
