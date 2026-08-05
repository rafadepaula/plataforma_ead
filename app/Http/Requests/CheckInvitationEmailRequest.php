<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-06 §3 — validates the public, unauthenticated AJAX payload for
 * `/convite/check-email`. Always authorized: this endpoint is deliberately
 * reachable by guests, gated by the route middleware group instead.
 */
class CheckInvitationEmailRequest extends FormRequest
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
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
