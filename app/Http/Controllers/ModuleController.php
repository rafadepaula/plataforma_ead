<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderModulesRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 *  Module CRUD, nested under a Course (`courses.modules`, shallow —
 * `index`/`create`/`store` are reached via `{course}`, `edit`/`update`/
 * `destroy` via `{module}` alone). `Module` has no `OrgScope` of its own
 * (see `courses-architecture`), so every action is guarded by
 * `ModulePolicy`, which independently verifies the parent Course's
 * `org_id`.
 */
class ModuleController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('viewAny', [Module::class, $course]);

        $modules = $course->modules()->withCount('lessons')->orderBy('order_index')->get();

        return view('courses.modules.index', ['course' => $course, 'modules' => $modules]);
    }

    public function create(Course $course): View
    {
        Gate::authorize('create', [Module::class, $course]);

        return view('courses.modules.create', ['course' => $course, 'module' => new Module]);
    }

    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $course->modules()->create($request->validated());

        return redirect()->route('courses.modules.index', $course)
            ->with('success', 'Módulo criado com sucesso.');
    }

    public function edit(Module $module): View
    {
        Gate::authorize('update', $module);

        return view('courses.modules.edit', ['course' => $module->course, 'module' => $module]);
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $module->update($request->validated());

        return redirect()->route('courses.modules.index', $module->course)
            ->with('success', 'Módulo atualizado com sucesso.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        Gate::authorize('delete', $module);

        $course = $module->course;

        // captured BEFORE the delete so title/id are
        // available; see `CourseController::destroy()` for the
        // `AuditableTrait` double-audit note.
        try {
            AuditService::log(
                event: 'content.deleted',
                orgId: $course->org_id ? (int) $course->org_id : null,
                userId: Auth::id(),
                auditableType: $module->getMorphClass(),
                auditableId: $module->id,
                payload: [
                    'model_type' => $module->getMorphClass(),
                    'model_id' => $module->id,
                    'title' => $module->title,
                    'deleted_by' => Auth::id(),
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        $module->delete();

        return redirect()->route('courses.modules.index', $course)
            ->with('success', 'Módulo removido com sucesso.');
    }

    /**
     *  AJAX reorder endpoint. `ReorderModulesRequest` only checks
     * that every id exists in `modules`; here we additionally confirm
     * every id belongs to `$course` before writing anything, otherwise a
     * Gestor could reorder (and thereby probe the existence of) another
     * org's rows by ID guessing. Reassigns a dense `0..n-1` `order_index`
     * sequence server-side rather than trusting client-supplied indexes,
     * since `order_index` has no unique DB constraint.
     */
    public function reorder(ReorderModulesRequest $request, Course $course): JsonResponse
    {
        $orderedIds = $request->validated()['ordered_ids'];

        $modules = $course->modules()->whereIn('id', $orderedIds)->get()->keyBy('id');

        if ($modules->count() !== count($orderedIds)) {
            return response()->json([
                'message' => 'Um ou mais módulos não pertencem a este curso.',
            ], 422);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $modules->get($id)->update(['order_index' => $index]);
        }

        return response()->json(['message' => 'Ordem dos módulos atualizada com sucesso.']);
    }
}
