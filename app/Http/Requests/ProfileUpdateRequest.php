<?php

namespace App\Http\Requests;

use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates a self-service profile update. Unlike
 * `UpdateUserRequest`, there is no `{user}` route parameter by design
 * : the target is always `$this->user()`, never a
 * route-bound model, so an authenticated user can only ever edit
 * their own record via this endpoint.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'cpf' => ['nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')->ignore($userId)],
        ];
    }
}
