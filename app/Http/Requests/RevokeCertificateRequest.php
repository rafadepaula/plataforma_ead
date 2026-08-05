<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-09 §1.2 — payload for the Gestor/Admin certificate revocation
 * action (`PUT certificates/{certificate}/revoke`). Authorization mirrors
 * `GradeEssayAnswerRequest`'s convention: delegated entirely to the
 * Policy via the route-bound model rather than re-checked in the
 * Controller.
 */
class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('revoke', $this->route('certificate'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revoke_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
