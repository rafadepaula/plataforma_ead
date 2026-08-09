<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SPEC-18 UC02 / RF34 — self-service profile management, available to any
 * authenticated user regardless of role. Unlike `UserController`
 * (Admin/Gestor managing OTHER users), this controller only ever acts on
 * `$request->user()` — there is no `{user}` route param by design (RN08),
 * and `org_id`/`status` are never accepted from request input (RN08/RN12).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->only(['name', 'email', 'cpf']));

        return redirect()->route('profile.edit')->with('success', 'Perfil atualizado com sucesso.');
    }
}
