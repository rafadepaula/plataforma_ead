<?php

namespace App\Models\Traits;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UnresolvedOrgContextException;
use App\Services\OrgContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Eloquent trait applied to every *directly* org-scoped model
 * (`Course`, `InvitationLink`, `ForumTopic`, `HelpArticle`).
 * Do NOT apply to `User`, `Credential` or to cascade-inherited models
 * (see the `tenancy-architecture` skill for the full list) — those inherit
 * their tenant boundary through a parent relation instead.
 *
 * Tenant context resolution (host-based tenancy):
 * - Admin (global account): filtered by `session('active_org_id')` when
 *   impersonating, unfiltered otherwise.
 * - Everyone else: the Organization resolved from the request host
 *   (`OrgContext`) — which is also the only Organization they could have
 *   logged in on.
 * - No resolved context (guest, console, state zero): org `0` never
 *   exists, so the filter reads as "empty set" — a safety fallback, not a
 *   feature.
 */
trait OrgScope
{
    protected static function bootOrgScope(): void
    {
        static::addGlobalScope('org', function (Builder $builder): void {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            if ($user->hasRole(RolesEnum::ADMIN->value)) {
                $activeOrgId = session('active_org_id');
                if ($activeOrgId) {
                    $builder->where($builder->getModel()->getTable().'.org_id', $activeOrgId);
                }

                return;
            }

            $builder->where($builder->getModel()->getTable().'.org_id', OrgContext::current()->orgId() ?? 0);
        });
    }

    protected static function booted(): void
    {
        static::creating(function ($model): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            // Always overwrite `org_id` with the server-resolved tenant
            // context, never trust a mass-assigned value from request
            // input (e.g. `Model::create($request->validated())`) — that
            // would allow a caller to inject a record into an arbitrary
            // organization, bypassing tenant isolation at write time.
            // Admins write into their impersonated Organization; every
            // other role writes into the Organization of the request host.
            $resolvedOrgId = $user->hasRole(RolesEnum::ADMIN->value)
                ? session('active_org_id')
                : OrgContext::current()->orgId();

            if (! $resolvedOrgId) {
                // No request is in flight (console, queue, test factories):
                // the context is not authoritative, so an explicitly given
                // org_id stands. Inside a real request the host always
                // resolves first — a state-zero write attempt must fail.
                if (! OrgContext::isBound() && $model->org_id !== null) {
                    return;
                }

                throw new UnresolvedOrgContextException(
                    'Não foi possível resolver org_id para criar '.static::class." (usuário #{$user->id} sem organização resolvida por host ou impersonação)."
                );
            }

            $model->org_id = $resolvedOrgId;
        });
    }
}
