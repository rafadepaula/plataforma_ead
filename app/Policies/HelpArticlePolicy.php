<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\User;

class HelpArticlePolicy
{
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
            return $article->org_id === null || $article->org_id === $user->org_id;
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
            return $article->org_id !== null && $article->org_id === $user->org_id;
        }

        return false;
    }

    public function delete(User $user, HelpArticle $article): bool
    {
        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            return $article->org_id !== null && $article->org_id === $user->org_id;
        }

        return false;
    }
}
