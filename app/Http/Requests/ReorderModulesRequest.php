<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

/**
 *  payload for the AJAX Module reorder endpoint. Authorized
 * against `ModulePolicy::create` (same `authorizeForCourse()` used by the
 * module CRUD) rather than `CoursePolicy::update`: an assigned Professor
 * reorders the Course's modules without being able to edit the Course's
 * own metadata — mirroring `StoreModuleRequest`/`ReorderLessonsRequest`
 * (which authorizes against `{module}` → `ModulePolicy` the same way).
 *
 * Only validates shape/existence here; `ModuleController::reorder()` is
 * responsible for confirming every id in `ordered_ids` actually belongs
 * to the route-bound `{course}` (an existence check alone would let a
 * Gestor reorder/leak another org's rows by ID guessing).
 */
class ReorderModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', [Module::class, $this->route('course')]);
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
