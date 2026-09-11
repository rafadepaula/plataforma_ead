<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Support\Facades\Session;

class HelpArticlePolicy
{
    /**
     * The Organization the acting user is operating on: the impersonating
     * Admin's `session('active_org_id')`, or the host Organization from
     * `OrgContext` for everyone else (mirrors `ResolvesOrgContext`). `null`
     * means no resolved Organization (global Admin or state-zero host).
     */
    private function resolvedOrgId(User $user): ?int
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return Session::get('active_org_id');
        }

        return OrgContext::current()->orgId();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function view(User $user, HelpArticle $article): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return $article->org_id === null || $article->org_id === $this->resolvedOrgId($user);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]);
    }

    public function update(User $user, HelpArticle $article): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return $article->org_id !== null && $article->org_id === $this->resolvedOrgId($user);
        }

        return false;
    }

    public function delete(User $user, HelpArticle $article): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return $article->org_id !== null && $article->org_id === $this->resolvedOrgId($user);
        }

        return false;
    }
}
