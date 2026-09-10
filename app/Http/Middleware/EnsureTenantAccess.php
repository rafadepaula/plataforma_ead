<?php

namespace App\Http\Middleware;

use App\Enums\Permissions\RolesEnum;
use App\Services\OrgContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant access gating on top of the `OrgContext` resolved by
 * `ResolveOrgFromHost`. Two states get special treatment (the third — a
 * matched, active Organization — passes straight through):
 *
 * - **State zero** (host matched nothing): a guest is bounced to the login
 *   (the only useful surface there — and only the global Admin account can
 *   pass it); an authenticated non-Admin is logged out; an authenticated
 *   Admin navigates freely (Organization CRUD lives in state zero).
 * - **Inactive Organization**: only public surfaces stay reachable — the
 *   landing and the certificate lookup (view-only); guests asking for
 *   anything else are bounced back to the landing; an authenticated
 *   non-Admin is logged out. Login/forgot/invite POSTs are NOT blocked
 *   here — they fail later with the generic auth failure (spec decision:
 *   no distinguishable error for a disabled tenant), which keeps
 *   information leakage at zero.
 */
class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = OrgContext::current();
        $user = $request->user();
        $isAdmin = $user?->hasRole(RolesEnum::ADMIN->value) ?? false;

        if ($context->isStateZero()) {
            if ($user !== null && ! $isAdmin) {
                Auth::guard('web')->logout();

                return redirect()
                    ->route('login')
                    ->with('error', trans('auth.failed'));
            }

            if ($user === null && ! $this->isAuthRoute($request)) {
                return redirect()->route('login');
            }

            return $next($request);
        }

        if (! $context->orgIsActive) {
            if ($user !== null && ! $isAdmin) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();

                return redirect('/')->with('error', trans('auth.failed'));
            }

            if ($user === null && ! $this->isPublicOnInactiveOrg($request)) {
                return redirect('/');
            }

            return $next($request);
        }

        return $next($request);
    }

    /**
     * Routes that must stay reachable for a guest in state zero — the
     * auth surfaces themselves (the Admin-only login and the password
     * reset round-trip) plus logout for the session teardown above.
     */
    private function isAuthRoute(Request $request): bool
    {
        return $request->is('login', 'logout', 'forgot-password', 'reset-password', 'forgot-password/*', 'reset-password/*');
    }

    /**
     * Surfaces an inactive tenant still serves to guests: the landing, the
     * public certificate lookup, AND the auth routes — login/forgot POSTs
     * must reach the provider so they fail with the generic auth error
     * (the spec mandates "qualquer tentativa de login resulta em falha de
     * autenticação", not a redirect). Everything else — including the
     * invite redemption flow — redirects to the landing.
     */
    private function isPublicOnInactiveOrg(Request $request): bool
    {
        return $request->is('/')
            || $this->isAuthRoute($request)
            || $request->is('validar-certificado', 'validar-certificado/*');
    }
}
