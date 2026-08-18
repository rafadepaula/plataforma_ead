<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * self-service password change. `Auth::logoutOtherDevices()`
 * is called before the password is rotated : it re-hashes and saves the
 * CURRENT (pre-rotation) password while invalidating every other session's
 * remember-token/session-password pairing, so a hijacked session does not
 * survive the change. `SESSION_DRIVER=database` is required for this to work.
 */
class PasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        Auth::logoutOtherDevices($request->string('current_password')->toString());

        $request->user()->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return redirect()->route('profile.edit')->with('success', 'Senha alterada com sucesso.');
    }
}
