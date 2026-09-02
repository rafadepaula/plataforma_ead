<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Events\EnrollmentConfirmed;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\StoreStudentEnrollmentRequest;
use App\Models\Course;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

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
     * JSON autocomplete feed for the enrollments panel's search box.
     * Matches an Aluno of the Course's own org by partial name, e-mail
     * or CPF (digits-normalized), excluding everyone already `active`ly
     * enrolled in this Course — a `cancelled` pivot row still matches and
     * comes back flagged, so the UI can offer reactivation through
     * `store()`'s existing re-enroll branch. Bounded to 10 hits because it
     * backs a dropdown, not a listing.
     */
    public function search(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $term = $validated['q'];
        $cpfDigits = Cpf::digits($term);

        $students = User::query()
            ->where('org_id', $course->org_id)
            ->whereHas('roles', fn ($query) => $query->where('name', RolesEnum::ALUNO->value))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->when($cpfDigits !== null, fn (Builder $query) => $query->orWhere('cpf', 'like', "%{$cpfDigits}%")))
            ->whereDoesntHave('courses', fn ($query) => $query
                ->where('course_user.course_id', $course->id)
                ->where('course_user.status', 'active'))
            ->with(['courses' => fn ($query) => $query->where('courses.id', $course->id)])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'cpf']);

        return response()->json([
            'data' => $students->map(fn (User $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'cpf' => $student->cpf,
                // `null` when never enrolled here, `cancelled` when the
                // existing pivot row may be reactivated.
                'enrollment_status' => $student->courses->first()?->pivot->status,
            ]),
        ]);
    }

    /**
     * "Cadastrar novo aluno" screen: a Course-nested creation form whose
     * submit ends with the new Aluno already enrolled in `$course` — the
     * manual counterpart of the CSV importer's create-and-enroll path,
     * reachable by Gestor. The Admin-only `users.create` stays untouched
     * (see `GestorStudentController`'s sibling note: staff accounts are an
     * Admin matter; new Alunos enter through invitation links, CSV import
     * or this panel).
     */
    public function create(Course $course): View
    {
        Gate::authorize('update', $course);

        return view('courses.enrollments.create', ['course' => $course]);
    }

    /**
     * creates the Aluno account in `$course`'s org AND enrolls it in
     * `$course`, in one transaction. There is no password input on this
     * flow: the CPF is the initial credential, hashed server-side from its
     * digits-normalized value. `EnrollmentConfirmed` dispatches only after
     * the transaction commits, so a notification can never go out for a
     * rolled-back enrollment.
     */
    public function storeStudent(StoreStudentEnrollmentRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        $data = $request->validated();
        $cpf = Cpf::digits($data['cpf']);

        $student = DB::transaction(function () use ($data, $course, $cpf): User {
            $student = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $cpf,
                'org_id' => $course->org_id,
                'password' => Hash::make($cpf),
                'status' => 'active',
            ]);
            $student->assignRole(RolesEnum::ALUNO->value);

            $course->students()->attach($student->id, [
                'enrolled_at' => now(),
                'status' => 'active',
            ]);

            return $student;
        });

        EnrollmentConfirmed::dispatch($course, $student);

        return redirect()->route('courses.enrollments.index', $course)
            ->with('success', 'Aluno cadastrado e matriculado com sucesso.');
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
