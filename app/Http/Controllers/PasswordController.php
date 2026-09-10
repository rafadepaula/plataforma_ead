<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Services\OrgContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * self-service password change, per-portal: the new password applies to
 * the `credentials` account of the request host's Organization only — the
 * person's accounts in other portals keep their own passwords. Rotating
 * the account's `remember_token` invalidates every remember-me cookie
 * issued by this portal (the `logoutOtherDevices` equivalent for
 * org-scoped credentials, where `users.password` no longer exists).
 */
class PasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $credential = $request->user()->credentialFor(OrgContext::current()->organization);

        if (! $credential) {
            abort(403, 'Conta não encontrada neste portal.');
        }

        $credential->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()->route('profile.edit')->with('success', 'Senha alterada com sucesso.');
    }
}
