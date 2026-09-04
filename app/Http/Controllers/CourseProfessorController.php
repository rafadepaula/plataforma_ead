<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\AttachCourseProfessorRequest;
use App\Models\Course;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Gestor/Admin per-course Professor assignment panel, nested under
 * `{course}` (`courses.professors.*`, `role:admin|gestor`), modeled on
 * {@see EnrollmentController} — including the "authorize nested actions
 * against the parent Course via `CoursePolicy::update`" convention, since
 * `course_professor` is a pivot with no model/policy of its own.
 *
 * Assignment is NOT enrollment: attaching writes only the
 * `course_professor` pivot (`course_user` is untouched), and the Admin
 * acting here is always impersonating the Course's Organization (or is
 * the Organization's own Gestor) — `Course`'s `OrgScope` on the route
 * binding already rejects a context-unresolved Admin.
 */
class CourseProfessorController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        Gate::authorize('update', $course);

        $assigned = $course->professors()
            ->orderBy('name')
            ->get();

        $assignedByNames = $this->assignedByNames($assigned->pluck('pivot.assigned_by')->filter()->unique());

        $searchInput = $request->input('q');
        $search = is_string($searchInput) ? trim($searchInput) : '';
        $cpfDigits = Cpf::digits($search);

        // same-org Professors not yet assigned to THIS Course — the
        // attachable pool. The pivot's UNIQUE(course_id, user_id) is the
        // base-level guard; `whereDoesntHave` keeps them out of the pool
        // so the button never offers a doomed insert.
        $available = User::query()
            ->where('org_id', $course->org_id)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RolesEnum::PROFESSOR->value))
            ->whereDoesntHave('taughtCourses', fn (Builder $query) => $query->where('course_professor.course_id', $course->id))
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->when($cpfDigits !== null, fn (Builder $query): Builder => $query->orWhereLike('cpf', "%{$cpfDigits}%"))))
            ->orderBy('name')
            ->get();

        return view('courses.professors.index', [
            'course' => $course,
            'assigned' => $assigned,
            'assignedByNames' => $assignedByNames,
            'available' => $available,
            'search' => $search,
        ]);
    }

    public function store(AttachCourseProfessorRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        // `syncWithoutDetaching` + the pivot's UNIQUE(course_id, user_id)
        // make re-assignment idempotent; `assigned_by` records who did it.
        $course->professors()->syncWithoutDetaching([
            $request->validated('user_id') => ['assigned_by' => Auth::id()],
        ]);

        return redirect()->route('courses.professors.index', $course)
            ->with('success', 'Professor atribuído ao curso com sucesso.');
    }

    public function destroy(Course $course, User $user): RedirectResponse
    {
        Gate::authorize('update', $course);

        $course->professors()->detach($user->id);

        return redirect()->route('courses.professors.index', $course)
            ->with('success', 'Atribuição do professor removida com sucesso.');
    }

    /**
     * One fetch resolving the `assigned_by` auditor ids of the assigned
     * rows into names for the view — never an N+1 per row.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, string>
     */
    protected function assignedByNames(Collection $userIds): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->all();
    }
}
