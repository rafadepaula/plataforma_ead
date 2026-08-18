<?php

namespace App\Http\Controllers;

use App\Exceptions\CourseHasActiveEnrollmentsException;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * RF06 — Course CRUD, reserved to `role:admin|gestor` (see `routes/web.php`
 * and `CoursePolicy`). `index`/`store`/etc. rely on the `OrgScope` global
 * scope to confine every query/write to the acting user's tenant — no
 * `org_id` filtering is done manually here.
 */
class CourseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Course::class);

        $courses = Course::query()
            ->orderBy('title')
            ->paginate(15);

        return view('courses.index', ['courses' => $courses]);
    }

    public function create(): View
    {
        Gate::authorize('create', Course::class);

        return view('courses.create', ['course' => new Course]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        // `org_id` is never set here — `OrgScope::booted()`'s `creating`
        // hook resolves it from the acting user's tenant context.
        Course::create($request->validated());

        return redirect()->route('courses.index')
            ->with('success', 'Curso criado com sucesso.');
    }

    public function edit(Course $course): View
    {
        Gate::authorize('update', $course);

        return view('courses.edit', ['course' => $course]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        return redirect()->route('courses.index')
            ->with('success', 'Curso atualizado com sucesso.');
    }

    /**
     * a Course with at least one `active` `course_user`
     * enrollment may never be soft-deleted. `CoursePolicy::delete()`
     * already denies this with a plain 403, but the explicit guard here
     * gives the caller the more descriptive 422 the acceptance criteria
     * calls for (mapped to a flashed Portuguese error message in
     * `bootstrap/app.php`).
     */
    public function destroy(Course $course): RedirectResponse
    {
        // `update` carries the same role/tenant check as `delete` but
        // without `CoursePolicy::delete()`'s active-enrollment clause —
        // used here purely as an authorization gate, so an unauthorized
        // caller (wrong role/org) still gets a plain 403 before the
        // business-rule guard below ever runs.
        Gate::authorize('update', $course);

        if ($course->hasActiveEnrollments()) {
            throw new CourseHasActiveEnrollmentsException(
                "Curso #{$course->id} possui matrículas ativas e não pode ser excluído."
            );
        }

        // Re-checked now that the enrollment guard has passed, so
        // `CoursePolicy::delete()` is still the final word on whether this
        // Course may actually be deleted.
        Gate::authorize('delete', $course);

        // `content.deleted` is captured BEFORE the delete so
        // the title/id are available; `Course` also carries `AuditableTrait`
        // (Bucket A), which independently fires a generic `course.deleted`
        // event from the same mutation — both are recorded under their own
        // event names.
        try {
            AuditService::log(
                event: 'content.deleted',
                orgId: $course->org_id ? (int) $course->org_id : null,
                userId: Auth::id(),
                auditableType: $course->getMorphClass(),
                auditableId: $course->id,
                payload: [
                    'model_type' => $course->getMorphClass(),
                    'model_id' => $course->id,
                    'title' => $course->title,
                    'deleted_by' => Auth::id(),
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        $course->delete();

        return redirect()->route('courses.index')
            ->with('success', 'Curso removido com sucesso.');
    }
}
