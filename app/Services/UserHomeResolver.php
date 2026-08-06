<?php

namespace App\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for resolving where a user should land after
 * authentication. Used by both AuthenticatedSessionController (post-login
 * redirect) and RedirectIfAuthenticated middleware (guest-guard redirect).
 */
class UserHomeResolver
{
    /**
     * Resolve the role-specific home URL for the given user.
     * Admin/Gestor -> admin.dashboard, Aluno -> student.courses.index, fallback /.
     */
    public function resolve(User $user): string
    {
        if ($user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return Route::has('admin.dashboard') ? route('admin.dashboard') : '/';
        }

        return Route::has('student.courses.index') ? route('student.courses.index') : '/';
    }
}
