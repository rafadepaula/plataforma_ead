<?php

namespace App\Models\Traits;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UnresolvedOrgContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Eloquent trait applied to every *directly* org-scoped model
 * (`Course`, `InvitationLink`, `ForumTopic`, `HelpArticle`,
 * `SystemSetting`). Do NOT apply to `User` or to cascade-inherited models
 * (see the `tenancy-architecture` skill for the full list) — those inherit
 * their tenant boundary through a parent relation instead.
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

            if ($user->org_id) {
                $builder->where($builder->getModel()->getTable().'.org_id', $user->org_id);
            } else {
                $builder->whereRaw('1 = 0');
            }
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
            $resolvedOrgId = $user->org_id ?? session('active_org_id');

            if (! $resolvedOrgId) {
                throw new UnresolvedOrgContextException(
                    'Não foi possível resolver org_id para criar '.static::class." (usuário #{$user->id} sem org_id e sem active_org_id em sessão)."
                );
            }

            $model->org_id = $resolvedOrgId;
        });
    }
}
