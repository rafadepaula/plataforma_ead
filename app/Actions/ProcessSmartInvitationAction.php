<?php

namespace App\Actions;

use App\Enums\Permissions\RolesEnum;
use App\Events\EnrollmentConfirmed;
use App\Exceptions\InvitationLinkInvalidException;
use App\Models\InvitationLink;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * consumes an `InvitationLink`: creates (or
 * authenticates) the `User`, enrolls them into the link's `course_id`, and
 * auto-logs them in. Runs inside a `lockForUpdate` transaction so two
 * concurrent requests against the same link at exactly `max_uses` cannot
 * both succeed — the usability check is re-verified *after* the lock is
 * acquired, not just by the caller before invoking this action.
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

            if (! $invitationLink || ! $invitationLink->isUsable()) {
                throw new InvitationLinkInvalidException("Convite '{$token}' inválido, expirado ou já utilizado.");
            }

            $user = User::query()->where('email', $data['email'])->first();

            if ($user) {
                // A staff account (gestor/admin) is not an "aluno" — the
                // self-service flow must never silently turn a staff
                // member into a student of a Course they may not even
                // belong to; rejected as a form-level error on `email`,
                // distinct from a wrong-password rejection.
                if ($user->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::ADMIN->value])) {
                    throw ValidationException::withMessages([
                        'email' => ['Este e-mail pertence a uma conta da equipe e não pode usar o convite de auto-matrícula.'],
                    ]);
                }

                // A wrong password is a form validation error, not an
                // invalid-link state — surfaced back to the `password`
                // field, never leaking whether the account exists (that
                // was already revealed, by design, by `/convite/check-email`
                // one step earlier in the flow).
                if (! Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'password' => ['Senha incorreta para o e-mail informado.'],
                    ]);
                }
            } else {
                $user = User::create([
                    'name' => $data['name'] ?? '',
                    'email' => $data['email'],
                    'cpf' => $data['cpf'] ?? null,
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    //  a student's `org_id` is set once, from their
                    // first invitation link ever consumed, and is never
                    // overwritten by a later invite from a different Org
                    // (tenancy for an already-enrolled student is derived
                    // from their `course_user` rows, not this field).
                    'org_id' => $invitationLink->org_id,
                ]);
                $user->assignRole(RolesEnum::ALUNO->value);
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
                // A previously revoked enrollment  is reactivated
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
