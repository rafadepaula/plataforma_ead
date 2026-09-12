<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\User;

/**
 * Help-article management is an Admin-only surface: the Gestor consumes the
 * public help wiki but never authors, edits, or deletes articles (the
 * `gestao/ajuda` routes are gated `role:admin` to match).
 */
class HelpArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function view(User $user, HelpArticle $article): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function update(User $user, HelpArticle $article): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }

    public function delete(User $user, HelpArticle $article): bool
    {
        return $user->hasRole(RolesEnum::ADMIN->value);
    }
}
