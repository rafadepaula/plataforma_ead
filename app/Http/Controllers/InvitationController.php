<?php

namespace App\Http\Controllers;

use App\Actions\ProcessSmartInvitationAction;
use App\Exceptions\InvitationLinkInvalidException;
use App\Http\Requests\CheckInvitationEmailRequest;
use App\Http\Requests\ProcessInvitationRequest;
use App\Models\InvitationLink;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/**
 * the public, unauthenticated Smart Invitation flow:
 * `/convite/{token}` (show + submit) and the `/convite/check-email` AJAX
 * lookup that drives the adaptive jQuery form (Bucket 3). No `Gate::
 * authorize` here — these routes are intentionally guest-reachable, see
 * `routes/web.php`'s dedicated unauthenticated group.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly ProcessSmartInvitationAction $processSmartInvitationAction) {}

    /**
     * @throws InvitationLinkInvalidException
     */
    public function show(string $token): View
    {
        $invitationLink = InvitationLink::query()
            ->withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if (! $invitationLink) {
            throw InvitationLinkInvalidException::notFound($token);
        }

        if ($reason = $invitationLink->unusableReason()) {
            throw InvitationLinkInvalidException::forReason($reason, $token);
        }

        return view('convite.show', [
            'invitationLink' => $invitationLink,
            // A visitor arriving from an invitation has no tenant session, so
            // the guest panel gets the inviting organization explicitly.
            'tenantName' => $invitationLink->organization?->name,
        ]);
    }

    public function checkEmail(CheckInvitationEmailRequest $request): JsonResponse
    {
        $exists = User::query()->where('email', $request->validated('email'))->exists();

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
}
