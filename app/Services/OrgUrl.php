<?php

namespace App\Services;

use App\Models\Organization;

/**
 * Absolute URLs that survive the queue: notifications are queued, so the
 * mail worker has no request and `route()` silently falls back to
 * `APP_URL` — the wrong portal for every Organization except the default
 * one. Org-hosted links must be built against the Organization's own
 * `host` instead.
 *
 * The scheme and port come from `APP_URL`, so local development keeps
 * working (`http://localhost.informatica:8080` with the /etc/hosts
 * mapping) while production hosts ride the standard port.
 */
final class OrgUrl
{
    public static function route(Organization|int|null $org, string $name, mixed ...$parameters): string
    {
        if (count($parameters) === 1 && is_array($parameters[0])) {
            $parameters = $parameters[0];
        }

        $path = route($name, $parameters, false);

        $host = match (true) {
            $org instanceof Organization => $org->host,
            is_int($org) => Organization::query()->whereKey($org)->value('host'),
            default => null,
        };

        if (! $host) {
            return url($path);
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return $scheme.'://'.$host.$port.$path;
    }
}
