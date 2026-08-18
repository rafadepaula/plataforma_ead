<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

/**
 *  validates creation of a Module nested under a Course. `course_id`
 * is intentionally absent from these rules: it is always resolved from the
 * route-bound `{course}` segment by `ModuleController::store()`, never
 * trusted from request input.
 */
class StoreModuleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ];
    }
}
