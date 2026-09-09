<?php

namespace App\Http\Controllers;

use App\Exceptions\CourseHasActiveEnrollmentsException;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Services\AuditService;
use App\Services\FileUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 *  Course CRUD, reserved to `role:admin|gestor` (see `routes/web.php`
 * and `CoursePolicy`). `index`/`store`/etc. rely on the `OrgScope` global
 * scope to confine every query/write to the acting user's tenant — no
 * `org_id` filtering is done manually here.
 */
class CourseController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Course::class);

        $searchInput = $request->input('search');
        $statusInput = $request->input('status');
        $search = is_string($searchInput) ? trim($searchInput) : '';
        $status = is_string($statusInput) && in_array($statusInput, ['all', 'published', 'draft'], true)
            ? $statusInput
            : 'all';

        $courses = Course::query()
            ->when(
                $search !== '',
                fn (Builder $query): Builder => $query->whereLike('title', "%{$search}%"),
            )
            ->when(
                $status === 'published',
                fn (Builder $query): Builder => $query->where('is_published', true),
            )
            ->when(
                $status === 'draft',
                fn (Builder $query): Builder => $query->where('is_published', false),
            )
            ->withCount([
                'modules',
                'lessons',
                'students',
                'students as active_students_count' => fn (Builder $query): Builder => $query
                    ->where('course_user.status', 'active'),
            ])
            ->orderBy('title')
            ->paginate(10)
            ->withQueryString();

        return view('courses.index', [
            'courses' => $courses,
            'search' => $search,
            'status' => $status,
        ]);
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
        $course->update($this->validatedAttributes($request));
        $this->syncCover($request, $course);

        return redirect()->route('courses.index')
            ->with('success', 'Curso atualizado com sucesso.');
    }

    /**
     * Strips the media-only inputs (`cover`/`remove_cover` — handled
     * after the Course exists by `syncCover()`) out of the validated
     * payload so they never reach mass assignment, mirroring
     * `LessonController::validatedAttributes()`.
     *
     * @return array<string, mixed>
     */
    private function validatedAttributes(UpdateCourseRequest $request): array
    {
        $data = $request->validated();
        unset($data['cover'], $data['remove_cover']);

        return $data;
    }

    /**
     * Applies the media-only request inputs against an already-persisted
     * Course. A newly uploaded `cover` wins over `remove_cover`: the new
     * file is stored first (via `FileUploadService`, isolated under the
     * Course's `org_id`), then the previous file is deleted from the
     * `public` disk, then the new `cover_path` is persisted. When the
     * persistence throws, the newly stored file is deleted again so no
     * orphan remains, and the exception is rethrown. Without a new file,
     * a truthy `remove_cover` deletes the current file and nulls
     * `cover_path`. With neither input, nothing is touched.
     */
    private function syncCover(Request $request, Course $course): void
    {
        $uploadedCover = $request->file('cover');

        if ($uploadedCover instanceof UploadedFile) {
            $previousPath = $course->cover_path;
            $storedPath = $this->fileUploadService->storeCover($uploadedCover, $course);

            try {
                $course->forceFill(['cover_path' => $storedPath])->save();
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($storedPath);

                throw $exception;
            }

            if (is_string($previousPath) && $previousPath !== '') {
                Storage::disk('public')->delete($previousPath);
            }

            return;
        }

        if ($request->boolean('remove_cover')) {
            if (is_string($course->cover_path) && $course->cover_path !== '') {
                Storage::disk('public')->delete($course->cover_path);
            }

            $course->forceFill(['cover_path' => null])->save();
        }
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
