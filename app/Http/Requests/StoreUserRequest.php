<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates creation of an Aluno/Gestor. `org_id` is intentionally
 * absent from these rules: it is always resolved server-side by
 * `UserController::resolveOrgId()`, never trusted from request input.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'cpf' => ['nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')],
            'role' => ['required', Rule::in([RolesEnum::ALUNO->value, RolesEnum::GESTOR->value])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
