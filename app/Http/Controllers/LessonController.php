<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderLessonsRequest;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\AuditService;
use App\Services\FileUploadService;
use App\Services\YoutubeSanitizerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 *  Lesson CRUD, nested under a Module (`modules.lessons`, shallow —
 * `index`/`create`/`store` are reached via `{module}`, `edit`/`update`/
 * `destroy` via `{lesson}` alone). `Lesson` is cascade-inherited two levels
 * deep (`module -> course.org_id`), so every action is guarded by
 * `LessonPolicy`. File/YouTube fields are delegated to
 * `FileUploadService`/`YoutubeSanitizerService` rather than handled
 * inline, keeping isolated-storage-path and embed-sanitization rules in
 * one place.
 */
class LessonController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected YoutubeSanitizerService $youtubeSanitizerService,
    ) {}

    public function index(Module $module): View
    {
        Gate::authorize('viewAny', [Lesson::class, $module]);

        $lessons = $module->lessons()->orderBy('order_index')->get();

        return view('modules.lessons.index', ['module' => $module, 'lessons' => $lessons]);
    }

    public function create(Module $module): View
    {
        Gate::authorize('create', [Lesson::class, $module]);

        return view('modules.lessons.create', ['module' => $module, 'lesson' => new Lesson]);
    }

    public function store(StoreLessonRequest $request, Module $module): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->handleMediaFields($request, $data, $module);

        $module->lessons()->create($data);

        return redirect()->route('modules.lessons.index', $module)
            ->with('success', 'Lição criada com sucesso.');
    }

    public function edit(Lesson $lesson): View
    {
        Gate::authorize('update', $lesson);

        return view('modules.lessons.edit', ['module' => $lesson->module, 'lesson' => $lesson]);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->handleMediaFields($request, $data, $lesson->module, $lesson);

        $lesson->update($data);

        return redirect()->route('modules.lessons.index', $lesson->module)
            ->with('success', 'Lição atualizada com sucesso.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $module = $lesson->module;

        // captured BEFORE the delete so title/id are
        // available; see `CourseController::destroy()` for the
        // `AuditableTrait` double-audit note.
        try {
            AuditService::log(
                event: 'content.deleted',
                orgId: $module->course?->org_id ? (int) $module->course->org_id : null,
                userId: Auth::id(),
                auditableType: $lesson->getMorphClass(),
                auditableId: $lesson->id,
                payload: [
                    'model_type' => $lesson->getMorphClass(),
                    'model_id' => $lesson->id,
                    'title' => $lesson->title,
                    'deleted_by' => Auth::id(),
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        // soft-delete only; `lesson_progress` rows must never be
        // cascade-purged by this action (see `courses-architecture`).
        $lesson->delete();

        return redirect()->route('modules.lessons.index', $module)
            ->with('success', 'Lição removida com sucesso.');
    }

    /**
     *  AJAX reorder endpoint, scoped to a Module. Same defense-in
     * -depth and dense-reassignment approach as
     * `ModuleController::reorder()`.
     */
    public function reorder(ReorderLessonsRequest $request, Module $module): JsonResponse
    {
        $orderedIds = $request->validated()['ordered_ids'];

        $lessons = $module->lessons()->whereIn('id', $orderedIds)->get()->keyBy('id');

        if ($lessons->count() !== count($orderedIds)) {
            return response()->json([
                'message' => 'Uma ou mais lições não pertencem a este módulo.',
            ], 422);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $lessons->get($id)->update(['order_index' => $index]);
        }

        return response()->json(['message' => 'Ordem das lições atualizada com sucesso.']);
    }

    /**
     * Resolves the `image`/`pdf` uploads (via `FileUploadService`, isolated
     * under the parent Course's `org_id`) and canonicalizes a non-empty
     * `youtube_url` (via `YoutubeSanitizerService`) into the validated
     * attributes, replacing any previously stored file on update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleMediaFields(
        StoreLessonRequest|UpdateLessonRequest $request,
        array $data,
        Module $module,
        ?Lesson $lesson = null,
    ): array {
        $course = $module->course;

        if ($request->hasFile('image')) {
            if ($lesson?->image_path) {
                Storage::disk('public')->delete($lesson->image_path);
            }
            $data['image_path'] = $this->fileUploadService->storeImage($request->file('image'), $course);
        }
        unset($data['image']);

        if ($request->hasFile('pdf')) {
            if ($lesson?->pdf_path) {
                Storage::disk('public')->delete($lesson->pdf_path);
            }
            $data['pdf_path'] = $this->fileUploadService->storePdf($request->file('pdf'), $course);
        }
        unset($data['pdf']);

        if (! empty($data['youtube_url'])) {
            $data['youtube_url'] = $this->youtubeSanitizerService->sanitize($data['youtube_url']);
        }

        return $data;
    }
}
