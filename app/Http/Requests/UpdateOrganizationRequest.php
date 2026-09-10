<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates an Organization update. Uniqueness rules
 * ignore the current record (route-bound `organization`).
 */
class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('organization')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = $this->route('organization')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'host' => [
                'nullable',
                'string',
                'max:253',
                'regex:/^[a-z0-9.-]+$/',
                Rule::unique('organizations', 'host')->ignore($organizationId),
            ],
            'landing_view' => ['nullable', 'string', 'max:100'],
            'cnpj' => [
                'nullable',
                'string',
                'regex:/^(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})$/',
                Rule::unique('organizations', 'cnpj')->ignore($organizationId),
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
