<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * sends the single-use password-reset token link via the
 * configured SMTP mailer (`config/mail.php`). Host-based tenancy: a reset
 * link is only sent when the person holds an account
 * (`credentials` row) in the request host's Organization — resetting from
 * portal B never touches portal A's password, and a disabled tenant
 * answers with the same generic "we can't find that e-mail" response.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the forgot-password view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming forgot-password request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (! $this->hasAccountInContextOrg($request->string('email')->toString())) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => trans(Password::INVALID_USER)]);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', trans($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => trans($status)]);
    }

    /**
     * Does this e-mail belong to a person holding the context
     * Organization's account? (`org_id = null` matches the global Admin
     * account — usable in state zero.) An inactive tenant deliberately
     * reads as "no account" — the failure message must not distinguish a
     * suspended portal from an unknown e-mail.
     */
    private function hasAccountInContextOrg(string $email): bool
    {
        $context = OrgContext::current();

        if ($context->organization !== null && ! $context->orgIsActive) {
            return false;
        }

        $user = User::query()->where('email', $email)->first();

        return $user !== null
            && Credential::query()->forOrg($context->orgId())->where('user_id', $user->id)->exists();
    }
}
