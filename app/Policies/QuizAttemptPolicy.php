<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * gates the Gestor's manual essay-grading screen. Scoped
 * to attempts whose `Quiz`'s cascade-inherited `org_id` matches the
 * Gestor's own — an Admin may grade any Org's attempts.
 */
class QuizAttemptPolicy
{
    public function view(User $user, QuizAttempt $attempt): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($attempt));
    }

    public function grade(User $user, QuizAttempt $attempt): bool
    {
        return $this->authorizeForCourse($user, $this->parentCourse($attempt));
    }

    /**
     * Loads the attempt's Course (via `quiz.lesson.module.course`)
     * bypassing `OrgScope`.
     */
    protected function parentCourse(QuizAttempt $attempt): Course
    {
        return $attempt->quiz->lesson->module->course()->withoutGlobalScopes()->firstOrFail();
    }

    /**
     * Admin: unrestricted. Gestor: only within their own Org. Professor
     * atribuído: pode ver/corrigir tentativas somente dos cursos a ele
     * atribuídos (`User::teaches()` — a pivot é o único filtro, mesmo
     * dentro da própria Organização).
     */
    protected function authorizeForCourse(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return $user->hasRole(RolesEnum::PROFESSOR->value) && $user->teaches($course);
        }

        if ($user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id !== (int) $course->org_id) {
            return false;
        }

        return true;
    }
}
