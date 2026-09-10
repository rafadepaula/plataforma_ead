<?php

namespace App\Actions;

use App\Enums\Permissions\RolesEnum;
use App\Events\EnrollmentConfirmed;
use App\Exceptions\InvitationLinkInvalidException;
use App\Models\Credential;
use App\Models\InvitationLink;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * consumes an `InvitationLink`: creates (or
 * authenticates) the person's account for the link's Organization, enrolls
 * them into the link's `course_id`, and auto-logs them in. Host-based
 * tenancy: the account operated on is the `credentials` row of the
 * request host's Organization — a person already enrolled in another
 * portal simply gets a new account here, with the password chosen on this
 * form. Runs inside a `lockForUpdate` transaction so two concurrent
 * requests against the same link at exactly `max_uses` cannot both
 * succeed.
 */
class ProcessSmartInvitationAction
{
    /**
     * @param  array{name?: string, cpf?: string, email: string, password: string}  $data
     *
     * @throws InvitationLinkInvalidException
     */
    public function execute(string $token, array $data): User
    {
        return DB::transaction(function () use ($token, $data) {
            $invitationLink = InvitationLink::query()
                ->withoutGlobalScopes()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $invitationLink) {
                throw InvitationLinkInvalidException::notFound($token);
            }

            // Wrong-host redemption reads as "not found" — same as the
            // controller's check, re-verified here after the lock.
            if ((int) $invitationLink->org_id !== (int) OrgContext::current()->orgId()) {
                throw InvitationLinkInvalidException::notFound($token);
            }

            // Re-checked *after* the lock, never before it: the reason is
            // resolved from the freshly locked row so a link exhausted by a
            // concurrent request reports "limite de vagas" and not the
            // state the caller read a moment earlier.
            if ($reason = $invitationLink->unusableReason()) {
                throw InvitationLinkInvalidException::forReason($reason, $token);
            }

            $user = User::query()->where('email', $data['email'])->first();

            if ($user) {
                // A staff account (gestor/admin) is not an "aluno" — the
                // self-service flow must never silently turn a staff
                // member into a student; rejected as a form-level error on
                // `email`, distinct from a wrong-password rejection.
                if ($user->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::ADMIN->value])) {
                    throw ValidationException::withMessages([
                        'email' => ['Este e-mail pertence a uma conta da equipe e não pode usar o convite de auto-matrícula.'],
                    ]);
                }

                $credential = Credential::query()
                    ->forOrg($invitationLink->org_id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($credential) {
                    // A wrong password is a form validation error, not an
                    // invalid-link state — surfaced back to the `password`
                    // field, never leaking whether the account exists.
                    if (! Hash::check($data['password'], $credential->password)) {
                        throw ValidationException::withMessages([
                            'password' => ['Senha incorreta para o e-mail informado.'],
                        ]);
                    }

                    // A deactivated account must never obtain a session,
                    // here exactly as in the login flow — checked *after*
                    // the password so the status of an account is never
                    // disclosed to someone who cannot authenticate into it.
                    if ($credential->status !== 'active') {
                        throw ValidationException::withMessages([
                            'email' => ['Esta conta está inativa. Procure o gestor da sua organização.'],
                        ]);
                    }
                } else {
                    // The person exists in another portal but holds no
                    // account here: the password chosen on this form
                    // creates THIS portal's account.
                    Credential::create([
                        'user_id' => $user->id,
                        'org_id' => $invitationLink->org_id,
                        'password' => Hash::make($data['password']),
                        'status' => 'active',
                    ]);
                }
            } else {
                $user = User::create([
                    'name' => $data['name'] ?? '',
                    'email' => $data['email'],
                    'cpf' => $data['cpf'] ?? null,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole(RolesEnum::ALUNO->value);

                Credential::create([
                    'user_id' => $user->id,
                    'org_id' => $invitationLink->org_id,
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                ]);
            }

            $enrollment = $user->courses()
                ->withoutGlobalScopes()
                ->wherePivot('course_id', $invitationLink->course_id)
                ->first();

            if (! $enrollment) {
                $user->courses()->attach($invitationLink->course_id, [
                    'enrolled_at' => now(),
                    'status' => 'active',
                ]);

                EnrollmentConfirmed::dispatch($invitationLink->course()->withoutGlobalScopes()->firstOrFail(), $user);
            } elseif ($enrollment->pivot->status === 'cancelled') {
                // A previously revoked enrollment is reactivated
                // rather than throwing on the `UNIQUE(user_id, course_id)`
                // constraint by attempting a second insert.
                $user->courses()->updateExistingPivot($invitationLink->course_id, [
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]);

                EnrollmentConfirmed::dispatch($invitationLink->course()->withoutGlobalScopes()->firstOrFail(), $user);
            }

            $invitationLink->increment('current_uses');

            Auth::login($user);

            // Session fixation guard, mirroring
            // `AuthenticatedSessionController::store()`. Guarded by
            // `hasSession()` so unit tests invoking this Action directly
            // (outside the HTTP kernel's session middleware) don't blow up
            // on a request with no session store bound.
            if (request()?->hasSession()) {
                request()->session()->regenerate();
            }

            return $user;
        });
    }
}
