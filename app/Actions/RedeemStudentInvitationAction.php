<?php

namespace App\Actions;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\InvitationInvalidException;
use App\Models\Credential;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * redeems a `StudentInvitation`: finalizes the
 * pre-registered Aluno's account for the invitation's Organization — sets
 * the org credential's password to the one chosen on the form and flips
 * it to `active` — then auto-logs them in. The identity (e-mail/name) is
 * NEVER taken from the request: it comes from the invitation's `user_id`,
 * which is exactly what makes the link unique per student and the e-mail
 * immutable. Runs inside a `lockForUpdate` transaction so two concurrent
 * requests against the same single-use token cannot both succeed.
 */
class RedeemStudentInvitationAction
{
    /**
     * @param  array{password: string}  $data
     *
     * @throws InvitationInvalidException
     */
    public function execute(string $token, array $data): User
    {
        return DB::transaction(function () use ($token, $data) {
            $invitation = StudentInvitation::query()
                ->withoutGlobalScopes()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                throw InvitationInvalidException::notFound($token);
            }

            // Wrong-host redemption reads as "not found" — same as the
            // controller's check, re-verified here after the lock.
            if ((int) $invitation->org_id !== (int) OrgContext::current()->orgId()) {
                throw InvitationInvalidException::notFound($token);
            }

            // Re-checked *after* the lock, never before it: the reason is
            // resolved from the freshly locked row so a token redeemed by
            // a concurrent request reports "já foi utilizado" and not the
            // state the caller read a moment earlier.
            if ($reason = $invitation->unusableReason()) {
                throw InvitationInvalidException::forReason($reason, $token);
            }

            $student = $invitation->student()->withoutGlobalScopes()->firstOrFail();

            // Invitations are only ever issued to Alunos (issuance is
            // gated to the Gestor's student surfaces); if the person was
            // promoted to staff after the link went out, the token must
            // not become a way to reset a staff password — reads as 404.
            if ($student->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::ADMIN->value])) {
                throw InvitationInvalidException::notFound($token);
            }

            // A Gestor-deactivated account must never obtain a session,
            // here exactly as in the login flow — the credential is left
            // untouched (still `inactive`) and the visitor gets the same
            // "procure o gestor" verdict they would get at the login
            // screen. A `pending` account, by contrast, is exactly what
            // this redemption exists to activate.
            $credential = Credential::query()
                ->forOrg($invitation->org_id)
                ->where('user_id', $student->id)
                ->first();

            if ($credential && $credential->status === 'inactive') {
                throw InvitationInvalidException::forReason(
                    InvitationInvalidException::REASON_INACTIVE,
                    $token
                );
            }

            // Missing credential (org account removed after issuance) is
            // recreated by the redemption itself — same semantics as the
            // Gestor issuing a fresh invitation for an existing person.
            if ($credential) {
                $credential->update([
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                ]);
            } else {
                Credential::create([
                    'user_id' => $student->id,
                    'org_id' => $invitation->org_id,
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                ]);
            }

            $invitation->update(['used_at' => now()]);

            Auth::login($student);

            // Session fixation guard, mirroring
            // `AuthenticatedSessionController::store()`. Guarded by
            // `hasSession()` so unit tests invoking this Action directly
            // (outside the HTTP kernel's session middleware) don't blow up
            // on a request with no session store bound.
            if (request()?->hasSession()) {
                request()->session()->regenerate();
            }

            return $student;
        });
    }
}
