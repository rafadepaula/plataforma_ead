<?php

namespace App\Http\View\Composers;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Single source of the role-aware root breadcrumb shared by every forum
 * screen (`forum.index`/`forum.create`/`forum.edit`/`forum.show`).
 *
 * `student.courses.index` is `role:aluno`-gated, yet these screens are also
 * reachable by a same-org Gestor/Admin (via `ForumTopicPolicy`), so the
 * root crumb has to resolve per role instead of 403-ing staff on
 * /meus-cursos. Role names come from `RolesEnum` — the same source of
 * truth `ForumTopicController::isGestorOrAdminForCourse()` reads — never
 * from string literals in a view.
 */
final class ForumBreadcrumbComposer
{
    /**
     * Bind the role-aware root crumb to the view as `$coursesCrumb`.
     */
    public function compose(View $view): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var array{label: string, url: string} $coursesCrumb */
        $coursesCrumb = $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])
            ? ['label' => 'Cursos', 'url' => route('courses.index')]
            : ['label' => 'Meus cursos', 'url' => route('student.courses.index')];

        $view->with('coursesCrumb', $coursesCrumb);
    }
}
