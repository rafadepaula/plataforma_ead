<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The public finalize-registration form behind a unique
 * `/convite/{token}` link. Deliberately carries NO identity fields: the
 * e-mail/name come from the invitation's pre-registered `user_id` and are
 * immutable — an attacker-controlled payload cannot redirect the
 * redemption to another person, and an extra `email` field in the POST
 * body is simply discarded by `validated()`.
 */
class FinalizeStudentInvitationRequest extends FormRequest
{
    /**
     * The route is guest-only and token-addressed; there is no
     * authenticated user to authorize, so the token itself (validated
     * again inside the Action's lock) is the authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Para concluir, marque a caixa de concordância acima.',
        ];
    }
}
