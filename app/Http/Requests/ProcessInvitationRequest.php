<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SPEC-06 §3 — validates the public, unauthenticated `/convite/{token}`
 * submission. Rules are conditional on whether the submitted e-mail
 * already exists (mirrors the adaptive jQuery form built in Bucket 3):
 * an existing e-mail only requires the password (verified against the
 * existing account by `ProcessSmartInvitationAction`), a new e-mail
 * additionally requires name/cpf/password-confirmation, matching
 * `StoreUserRequest`'s registration-field shape since no dedicated
 * self-registration `RegisterRequest` exists yet in this codebase.
 */
class ProcessInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $emailExists = $this->emailExists();

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => [Rule::requiredIf(! $emailExists), 'nullable', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', new Cpf],
            'password' => $emailExists
                ? ['required', 'string']
                : ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function emailExists(): bool
    {
        $email = $this->string('email')->toString();

        if ($email === '') {
            return false;
        }

        return User::query()->where('email', $email)->exists();
    }
}
