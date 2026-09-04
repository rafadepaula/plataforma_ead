<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates creation of a Professor by the Gestor
 * (`gestor.professors.store`). Deliberately mirrors
 * {@see StoreUserRequest}'s shape: `org_id` is intentionally absent from
 * these rules — it is always resolved server-side by
 * `GestorProfessorController::resolveOrgId()`, never trusted from request
 * input — and `role` is absent because the created account is always a
 * `professor` by construction of the screen.
 */
class StoreGestorProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /**
     * @see Cpf::digits()
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('cpf')) {
            $this->merge(['cpf' => Cpf::digits($this->input('cpf'))]);
        }
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
