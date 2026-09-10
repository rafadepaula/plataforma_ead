<?php

namespace App\Http\Requests\Auth;

use App\Services\OrgContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * validates email/password, throttles repeated failed
 * attempts per (email, org, ip), and delegates credential validation to
 * `OrgCredentialUserProvider`: the password is checked against the
 * `credentials` row of the request host's Organization, so a person with
 * no account in the portal they are visiting fails auth exactly like a
 * wrong password would.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Status/password checks live in the provider's per-org
        // `credentials` lookup — an inactive account, an account of another
        // Organization, or a disabled tenant all collapse into the same
        // generic `auth.failed` outcome.
        if (! Auth::attempt(
            $this->only('email', 'password'),
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request — scoped to the
     * host Organization (`global` in state zero) so that attempts against
     * one portal never lock someone out of another.
     */
    public function throttleKey(): string
    {
        $orgKey = OrgContext::current()->orgId() ?? 'global';

        return Str::transliterate(Str::lower($this->string('email')).'|'.$orgKey.'|'.$this->ip());
    }
}
