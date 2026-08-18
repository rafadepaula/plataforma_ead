<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 *  validates an update to a Module. Mirrors
 * {@see StoreModuleRequest}; authorization is scoped to the route-bound
 * `module` instance so `ModulePolicy::update()` can verify the parent
 * Course's `org_id` (defense in depth on top of `OrgScope`, since `Module`
 * itself is cascade-inherited and carries no `org_id` column).
 */
class UpdateModuleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ];
    }
}
