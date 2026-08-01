<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Permissions\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-04 RF01 — login form, session creation and logout.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPathFor($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Clear any Admin "Impersonate Org" context (SPEC-04 §2) — an
        // impersonated org must never leak into the next session.
        $request->session()->forget('active_org_id');

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Resolve where a freshly authenticated user should land, based on
     * their Spatie role. Falls back to `/` until the role-specific
     * dashboards (built in later SPEC-04 buckets) exist.
     */
    private function redirectPathFor(User $user): string
    {
        if ($user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return Route::has('admin.dashboard') ? route('admin.dashboard') : '/';
        }

        return Route::has('student.courses.index') ? route('student.courses.index') : '/';
    }
}
