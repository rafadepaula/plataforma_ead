<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\OrgContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant resolver: the FIRST middleware of the `web` group. Maps the
 * request host (lowercased, port stripped — `getHost()` already removes
 * the port) to an `Organization` through the exact-match
 * `organizations.host` column and binds the request's `OrgContext`
 * singleton. Soft-deleted organizations never resolve (their host is
 * state zero).
 *
 * This middleware performs no redirecting — gating lives in
 * `EnsureTenantAccess`, which runs after the group's session middleware.
 */
class ResolveOrgFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->normalizeHost($request);

        $organization = $host === ''
            ? null
            : Organization::query()->where('host', $host)->first();

        app()->instance(OrgContext::class, new OrgContext(
            organization: $organization,
            orgIsActive: $organization !== null && $organization->status === 'active',
        ));

        return $next($request);
    }

    /**
     * The tenant key comes from the raw `Host` header, NOT from
     * `Request::getHost()`: Symfony prefers `SERVER_NAME`, which behind a
     * misconfigured vhost (or in the test client, which defaults it to
     * `localhost`) does not reflect the host the browser actually asked
     * for. Port is stripped (`portal.acme.test:8080` →
     * `portal.acme.test`); IPv6 literals keep their brackets. Empty header
     * (console) means state zero.
     */
    private function normalizeHost(Request $request): string
    {
        $host = trim((string) $request->header('Host', ''));

        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            $host = substr($host, 0, (int) strpos($host, ']') + 1);
        } else {
            $host = Str::before($host, ':');
        }

        return Str::lower($host);
    }
}
