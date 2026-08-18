<?php

namespace App\Http\Controllers;

use App\Events\EnrollmentConfirmed;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Gestor/Admin panel for manually enrolling/revoking a
 * Course's `course_user` rows, nested under `{course}` (`index`/`store`
 * reached via `{course}` alone, `destroy` via `{course}` + `{user}` — not a
 * `Route::resource()`, see `routes/web.php`). No separate `Enrollment`
 * model/policy exists — `course_user` is a pivot only (see
 * `courses-architecture`), so every action is authorized against the
 * parent `Course` via `CoursePolicy::update`, matching
 * `ModulePolicy`/`LessonPolicy`'s "authorize nested actions against the
 * parent Course" convention.
 */
class EnrollmentController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('update', $course);

        $enrollments = $course->students()->orderBy('name')->paginate(20);

        return view('courses.enrollments.index', ['course' => $course, 'enrollments' => $enrollments]);
    }

    /**
     *  manually enrolls an existing `User` into `$course`. Uses
     * `firstOrCreate` keyed on the `[user_id, course_id]` pair the
     * `course_user` table's `UNIQUE` constraint enforces, so re-enrolling
     * a previously `cancelled` student reactivates the existing pivot row
     * instead of attempting (and failing) a duplicate insert.
     */
    public function store(StoreEnrollmentRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        $userId = $request->validated('user_id');

        if ($course->students()->where('users.id', $userId)->exists()) {
            $course->students()->updateExistingPivot($userId, [
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        } else {
            $course->students()->attach($userId, [
                'enrolled_at' => now(),
                'status' => 'active',
            ]);
        }

        // `StoreEnrollmentRequest`'s `unique(course_user, status = active)`
        // rule already guarantees this branch is only ever reached on a
        // brand-new pivot row or a reactivated (previously `cancelled`)
        // one — never an already-active, unchanged enrollment — so it is
        // always safe to notify here without double-notifying.
        EnrollmentConfirmed::dispatch($course, User::query()->findOrFail($userId));

        return redirect()->route('courses.enrollments.index', $course)
            ->with('success', 'Aluno matriculado com sucesso.');
    }

    /**
     *  revokes a student's enrollment by setting the `course_user`
     * pivot's `status` to `cancelled`, matching the pivot's soft-status
     * design; never detaches the row (that would lose enrollment history
     * and re-open the `UNIQUE(user_id, course_id)` slot for a duplicate
     * insert instead of a clean reactivation later).
     */
    public function destroy(Course $course, User $user): RedirectResponse
    {
        Gate::authorize('update', $course);

        $course->students()->updateExistingPivot($user->id, ['status' => 'cancelled']);

        return redirect()->route('courses.enrollments.index', $course)
            ->with('success', 'Matrícula revogada com sucesso.');
    }
}
