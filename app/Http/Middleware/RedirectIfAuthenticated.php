<?php

namespace App\Http\Middleware;

use App\Services\UserHomeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom RedirectIfAuthenticated that replaces the framework default.
 * Uses UserHomeResolver to provide role-aware redirect targets instead
 * of the default `/` or `home` route fallback.
 */
class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Uses the parent middleware's guard-checking logic but overrides the
     * redirect target with the role-aware home page from UserHomeResolver.
     * Honors `url.intended` in the session via `redirect()->intended()`.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $user = null;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                break;
            }
        }

        if ($user === null && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
        }

        if ($user !== null) {
            $home = app(UserHomeResolver::class)->resolve($user);

            return redirect()->intended($home);
        }

        return $next($request);
    }
}
