<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates a new Organization submission. `host` is
 * the tenant key (exact `HTTP_HOST`, lowercase, port stripped) and
 * `landing_view` names the Organization's landing blade; both are
 * admin-only fields on the CRUD.
 */
class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Organization::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'host' => [
                'nullable',
                'string',
                'max:253',
                'regex:/^[a-z0-9.-]+$/',
                'unique:organizations,host',
            ],
            'landing_view' => ['nullable', 'string', 'max:100'],
            'cnpj' => [
                'nullable',
                'string',
                'regex:/^(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})$/',
                'unique:organizations,cnpj',
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
