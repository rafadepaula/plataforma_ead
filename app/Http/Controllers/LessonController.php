<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderLessonsRequest;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Module;
use App\Services\AuditService;
use App\Services\FileUploadService;
use App\Services\VideoUrlSanitizerManager;
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
 * `LessonPolicy`. File/video fields are delegated to
 * `FileUploadService`/`VideoUrlSanitizerManager` rather than handled
 * inline, keeping isolated-storage-path and embed-sanitization rules in
 * one place.
 */
class LessonController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected VideoUrlSanitizerManager $videoUrlSanitizers,
    ) {}

    public function index(Module $module): View
    {
        Gate::authorize('viewAny', [Lesson::class, $module]);

        $lessons = $module->lessons()->with('media')->orderBy('order_index')->get();

        return view('modules.lessons.index', ['module' => $module, 'lessons' => $lessons]);
    }

    public function create(Module $module): View
    {
        Gate::authorize('create', [Lesson::class, $module]);

        return view('modules.lessons.create', ['module' => $module, 'lesson' => new Lesson]);
    }

    public function store(StoreLessonRequest $request, Module $module): RedirectResponse
    {
        $lesson = $module->lessons()->create($this->validatedAttributes($request));
        $this->syncMedia($request, $lesson);

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
        $lesson->update($this->validatedAttributes($request));
        $this->syncMedia($request, $lesson);

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
     * Strips the media-only inputs (`images[]`/`pdfs[]`/`removed_media[]` —
     * handled after the Lesson exists by `syncMedia()`) out of the validated
     * payload, canonicalizes a non-empty `video_url` through the sanitizer
     * of its `video_provider` (detected from the URL itself when the select
     * came empty) and nulls both video fields out together when the URL is
     * cleared — a lesson never keeps a provider stamp without a URL.
     *
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreLessonRequest|UpdateLessonRequest $request): array
    {
        $data = $request->validated();
        unset($data['images'], $data['pdfs'], $data['removed_media']);

        // Switch desmarcado não é enviado no request, então normaliza para
        // `false` — sem isso, despublicar nunca persistiria e publicar
        // dependeria só da validação aceitar o campo.
        $data['is_published'] = $request->boolean('is_published');

        if (blank($data['video_url'] ?? null)) {
            $data['video_url'] = null;
            $data['video_provider'] = null;

            return $data;
        }

        $provider = $data['video_provider'] ?? $this->videoUrlSanitizers->providerFor($data['video_url']);
        $data['video_url'] = $this->videoUrlSanitizers->for((string) $provider)->sanitize((string) $data['video_url']);
        $data['video_provider'] = $provider;

        return $data;
    }

    /**
     * Applies the media-only request inputs against an already-persisted
     * Lesson: deletes the attachments listed in `removed_media[]` (ids
     * belonging to any other Lesson are silently ignored — a cross-tenant
     * probe must never delete another org's files), then appends a
     * `LessonMedia` row per uploaded `images[]`/`pdfs[]` file (via
     * `FileUploadService`, isolated under the parent Course's `org_id`).
     *
     * Uploads are additive: a new image never replaces an existing one —
     * per-attachment removal is what `removed_media[]` is for. Finally the
     * deprecated legacy `image_path`/`pdf_path` columns are re-synced to the
     * first attachment of each kind (or `null` once none remain) so
     * not-yet-migrated read paths keep working. That resync uses
     * `saveQuietly()`: it is bookkeeping derived from the media rows just
     * written, not a distinct user action, so it must not fire a second
     * `AuditObserver` "updated" row on top of the create()/update() the
     * caller already logged for this same request.
     */
    private function syncMedia(StoreLessonRequest|UpdateLessonRequest $request, Lesson $lesson): void
    {
        $course = $lesson->module->course;
        $changed = false;

        $removedIds = array_values(array_map('intval', (array) $request->input('removed_media', [])));

        if ($removedIds !== []) {
            // Ownership guard: scoping to the Lesson's own media means an id
            // belonging to another Lesson (possibly another org's) is ignored.
            $lesson->media()->whereIn('id', $removedIds)->get()
                ->each(function (LessonMedia $media): void {
                    Storage::disk('public')->delete($media->path);
                    $media->delete();
                });
            $changed = true;
        }

        $imageFiles = array_values((array) $request->file('images', []));

        if ($imageFiles !== []) {
            foreach ($this->fileUploadService->storeImages($imageFiles, $course) as $index => $path) {
                $file = $imageFiles[$index];
                $lesson->media()->create([
                    'kind' => LessonMedia::KIND_IMAGE,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size_bytes' => $file->getSize(),
                ]);
            }
            $changed = true;
        }

        $pdfFiles = array_values((array) $request->file('pdfs', []));

        if ($pdfFiles !== []) {
            foreach ($this->fileUploadService->storePdfs($pdfFiles, $course) as $index => $path) {
                $file = $pdfFiles[$index];
                $lesson->media()->create([
                    'kind' => LessonMedia::KIND_PDF,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size_bytes' => $file->getSize(),
                ]);
            }
            $changed = true;
        }

        if ($changed) {
            $lesson->forceFill([
                'image_path' => $lesson->images()->orderBy('id')->value('path'),
                'pdf_path' => $lesson->pdfs()->orderBy('id')->value('path'),
            ])->saveQuietly();
        }
    }
}
