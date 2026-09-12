<?php

namespace App\Http\Controllers;

use App\Actions\RedeemStudentInvitationAction;
use App\Exceptions\InvitationInvalidException;
use App\Http\Requests\FinalizeStudentInvitationRequest;
use App\Models\StudentInvitation;
use App\Services\OrgContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/**
 * the public, unauthenticated invitation redemption flow:
 * `/convite/{token}` (show + submit). The token IS the identity — each
 * `StudentInvitation` row is bound to one pre-registered Aluno, so the
 * e-mail/name on the form come from the invitation and are immutable; the
 * visitor only chooses a password and consents. Host-based tenancy: a
 * link is only redeemable on its own Organization's host — a valid token
 * hit from another portal reads as "not found", never revealing the link.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly RedeemStudentInvitationAction $redeemStudentInvitationAction) {}

    /**
     * @throws InvitationInvalidException
     */
    public function show(string $token): View
    {
        $invitation = $this->resolveUsableInvitation($token);

        // Welcome screen, not a bureaucratic form: the visitor sees the
        // courses the Gestor already enrolled them in — the account comes
        // ready-made, only the password is missing.
        $courseTitles = $invitation->student
            ->courses()
            ->withoutGlobalScopes()
            ->where('courses.org_id', $invitation->org_id)
            ->wherePivotIn('status', ['active', 'completed'])
            ->orderBy('courses.title')
            ->pluck('courses.title');

        return view('convite.show', [
            'invitation' => $invitation,
            'courseTitles' => $courseTitles,
            // A visitor arriving from an invitation has no tenant session, so
            // the guest panel gets the inviting organization explicitly.
            'tenantName' => $invitation->organization?->name,
        ]);
    }

    /**
     * @throws InvitationInvalidException
     */
    public function store(FinalizeStudentInvitationRequest $request, string $token): RedirectResponse
    {
        $this->redeemStudentInvitationAction->execute($token, $request->validated());

        return redirect(
            Route::has('student.courses.index') ? route('student.courses.index') : '/'
        )->with('success', 'Cadastro finalizado com sucesso. Bem-vindo!');
    }

    /**
     * Token → usable invitation, restricted to the request host's
     * Organization: wrong-portal and unknown tokens are indistinguishable
     * 404s.
     *
     * @throws InvitationInvalidException
     */
    private function resolveUsableInvitation(string $token): StudentInvitation
    {
        $invitation = StudentInvitation::query()
            ->withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            throw InvitationInvalidException::notFound($token);
        }

        if ((int) $invitation->org_id !== (int) OrgContext::current()->orgId()) {
            throw InvitationInvalidException::notFound($token);
        }

        if ($reason = $invitation->unusableReason()) {
            throw InvitationInvalidException::forReason($reason, $token);
        }

        return $invitation;
    }
}
