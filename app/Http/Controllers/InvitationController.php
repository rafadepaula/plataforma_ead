<?php

namespace App\Http\Controllers;

use App\Actions\ProcessSmartInvitationAction;
use App\Exceptions\InvitationLinkInvalidException;
use App\Http\Requests\CheckInvitationEmailRequest;
use App\Http\Requests\ProcessInvitationRequest;
use App\Models\Credential;
use App\Models\InvitationLink;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/**
 * the public, unauthenticated Smart Invitation flow:
 * `/convite/{token}` (show + submit) and the `/convite/check-email` AJAX
 * lookup that drives the adaptive form. Host-based tenancy: a link is only
 * redeemable on its own Organization's host — a valid token hit from
 * another portal reads as "not found", never revealing the link. The
 * adaptive form keys on the person holding an account
 * (`credentials` row) in THIS portal's Organization.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly ProcessSmartInvitationAction $processSmartInvitationAction) {}

    /**
     * @throws InvitationLinkInvalidException
     */
    public function show(string $token): View
    {
        $invitationLink = $this->resolveUsableLink($token);

        return view('convite.show', [
            'invitationLink' => $invitationLink,
            // A visitor arriving from an invitation has no tenant session, so
            // the guest panel gets the inviting organization explicitly.
            'tenantName' => $invitationLink->organization?->name,
        ]);
    }

    public function checkEmail(CheckInvitationEmailRequest $request): JsonResponse
    {
        $context = OrgContext::current();
        $user = User::query()->where('email', $request->validated('email'))->first();

        $exists = $user !== null
            && Credential::query()->forOrg($context->orgId())->where('user_id', $user->id)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * @throws InvitationLinkInvalidException
     */
    public function store(ProcessInvitationRequest $request, string $token): RedirectResponse
    {
        $this->processSmartInvitationAction->execute($token, $request->validated());

        return redirect(
            Route::has('student.courses.index') ? route('student.courses.index') : '/'
        )->with('success', 'Matrícula realizada com sucesso.');
    }

    /**
     * Token → usable link, restricted to the request host's Organization:
     * wrong-portal and unknown tokens are indistinguishable 404s.
     *
     * @throws InvitationLinkInvalidException
     */
    private function resolveUsableLink(string $token): InvitationLink
    {
        $invitationLink = InvitationLink::query()
            ->withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if (! $invitationLink) {
            throw InvitationLinkInvalidException::notFound($token);
        }

        if ((int) $invitationLink->org_id !== (int) OrgContext::current()->orgId()) {
            throw InvitationLinkInvalidException::notFound($token);
        }

        if ($reason = $invitationLink->unusableReason()) {
            throw InvitationLinkInvalidException::forReason($reason, $token);
        }

        return $invitationLink;
    }
}
