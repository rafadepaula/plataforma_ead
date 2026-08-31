<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates the public, unauthenticated `/convite/{token}`
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
     * Normalises the CPF to digits only before any rule runs, so the
     * uniqueness check below (and the value later persisted by
     * `ProcessSmartInvitationAction`) can never be defeated by the same
     * document typed with the `000.000.000-00` mask.
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
        $existingUser = $this->existingUser();
        $emailExists = $existingUser !== null;

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => [Rule::requiredIf(! $emailExists), 'nullable', 'string', 'max:255'],
            // The uniqueness check ignores the account that owns the
            // submitted e-mail: without JavaScript (or when
            // `/convite/check-email` fails and the form degrades to the
            // new-account state) an already-registered student still posts
            // their own CPF, and rejecting it would lock them out of the
            // enrollment entirely.
            'cpf' => [Rule::requiredIf(! $emailExists), 'nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')->ignore($existingUser?->id)],
            'password' => $emailExists
                ? ['required', 'string']
                : ['required', 'string', 'min:8', 'confirmed'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'É necessário concordar para concluir a matrícula.',
        ];
    }

    /**
     * The account that already owns the submitted e-mail, if any.
     */
    protected function existingUser(): ?User
    {
        $email = $this->string('email')->toString();

        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }
}
