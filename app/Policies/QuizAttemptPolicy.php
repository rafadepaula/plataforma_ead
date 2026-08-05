<?php

namespace App\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * SPEC-08 §2.1 — gates the Gestor's manual essay-grading screen. Scoped
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

    protected function authorizeForCourse(User $user, Course $course): bool
    {
        if (! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value])) {
            return false;
        }

        if ($user->hasRole(RolesEnum::GESTOR->value) && (int) $user->org_id !== (int) $course->org_id) {
            return false;
        }

        return true;
    }
}
