<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * "Meus Cursos": an Aluno's own enrollments, grouped by
 * `org_id`. Restricted to `role:aluno` (see `routes/web.php`) — not
 * `student.enrolled`, since this listing IS the enrollment data itself,
 * with no single `{course}`/`{lesson}` route parameter to gate. Reads
 * across every Organization the student is enrolled in, intentionally
 * bypassing `OrgScope` (a student may hold enrollments in Courses from
 * more than one Organization) — `withoutGlobalScopes()` is required here
 * since an Aluno carries no `org_id` of their own, which would otherwise
 * make `Course`'s `OrgScope` filter every enrollment out (mirrors
 * `User::hasActiveOrCompletedEnrollment()`'s same convention). Only
 * `active`/`completed` pivot statuses are listed — a `cancelled`
 * enrollment (e.g. via `EnrollmentController::destroy()`) is excluded so
 * this page never shows a course `EnsureStudentIsEnrolled` would 403 on.
 */
class StudentCourseController extends Controller
{
    public function index(): View
    {
        $courses = Auth::user()->courses()
            ->withoutGlobalScopes()
            ->wherePivotIn('status', ['active', 'completed'])
            ->with('organization')
            ->get()
            ->groupBy('org_id');

        return view('student.courses.index', ['courses' => $courses]);
    }
}
