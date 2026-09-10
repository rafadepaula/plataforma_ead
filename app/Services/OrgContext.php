<?php

namespace App\Services;

use App\Models\Organization;

/**
 * Immutable per-request tenant context resolved from the request host by
 * `ResolveOrgFromHost`. This is the single source of truth for "which
 * Organization am I serving right now" — it is bound per request and never
 * persisted to the session (the session only ever carries the Admin's
 * `active_org_id` impersonation).
 *
 * - `organization = null` is "state zero": the host matched nothing (direct
 *   IP, unknown domain, or an Organization without a host). Only the global
 *   Admin account is usable there.
 * - `orgIsActive = false` on a matched Organization means the tenant is
 *   `inactive`: the landing stays viewable, but every auth surface fails.
 *
 * `current()` is safe outside the web middleware (console, queue, early
 * container access): anything before `ResolveOrgFromHost` runs is state
 * zero by definition.
 */
final class OrgContext
{
    public function __construct(
        public readonly ?Organization $organization,
        public readonly bool $orgIsActive,
    ) {}

    /**
     * The bound context of the current request, or the state-zero fallback
     * when no request middleware has resolved a host yet.
     */
    public static function current(): self
    {
        $instance = app()->bound(self::class) ? app(self::class) : null;

        return $instance ?? self::stateZero();
    }

    public static function stateZero(): self
    {
        return new self(null, false);
    }

    /**
     * True when the host matched no Organization (or none was resolved —
     * console context).
     */
    public function isStateZero(): bool
    {
        return $this->organization === null;
    }

    /**
     * Convenience for org-scoped queries and credential lookups. `null`
     * targets the global Admin account (`credentials.org_id = null`).
     */
    public function orgId(): ?int
    {
        return $this->organization?->id;
    }
}
