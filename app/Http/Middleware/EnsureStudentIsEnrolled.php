<?php

namespace App\Http\Middleware;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * gates the student-facing classroom/lesson/progress routes.
 * Resolves the `Course` from either a `{course}` or `{lesson}` route
 * parameter (supporting both bucket-2 route shapes), then:
 *
 * - Admin: always allowed (no impersonation requirement here — this guard
 *   is about enrollment, not tenant management).
 * - Gestor: allowed only when their own `org_id` matches the Course's.
 * - Aluno: allowed only with a `course_user` row in `active`/`completed`
 *   status — a `cancelled` status or no row at all is denied. A denied
 *   page request is sent back to the course catalog with an explanatory
 *   alert; API-shaped requests (JSON/AJAX progress endpoints) still get a
 *   bare 403 because they have nowhere to redirect to.
 */
class EnsureStudentIsEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $course = $this->resolveCourse($request);

        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return $next($request);
        }

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            abort_unless((int) $user->org_id === (int) $course->org_id, 403);

            return $next($request);
        }

        if (! $user->hasActiveOrCompletedEnrollment($course)) {
            return $this->denyEnrollment($request);
        }

        return $next($request);
    }

    /**
     * Sends an Aluno without an active enrollment back to their course
     * catalog carrying the denial alert. Requests that expect a payload
     * keep the plain 403 status they can actually handle.
     */
    protected function denyEnrollment(Request $request): Response
    {
        abort_if($request->expectsJson(), 403);

        return redirect()
            ->route('student.courses.index')
            ->with('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    protected function resolveCourse(Request $request): Course
    {
        $courseParam = $request->route('course');

        if ($courseParam instanceof Course) {
            return $courseParam;
        }

        if ($courseParam !== null) {
            return Course::query()->withoutGlobalScopes()->findOrFail($courseParam);
        }

        $lessonParam = $request->route('lesson');

        $lesson = $lessonParam instanceof Lesson
            ? $lessonParam
            : Lesson::query()->findOrFail($lessonParam);

        return $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
    }
}
