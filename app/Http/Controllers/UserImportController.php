<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\ImportUsersChunkRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\UserImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF05/RN09 — GET renders the upload form (course picker + file input);
 * POST /users/import/chunk is hit repeatedly by `CsvImporter.js`, once per
 * client-side 50-row batch, until the whole CSV has streamed through.
 */
class UserImportController extends Controller
{
    use ResolvesOrgContext;

    public function __construct(private readonly UserImportService $importService) {}

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        $orgId = $this->resolveOrgId($request);
        $courses = Course::query()->where('org_id', $orgId)->orderBy('title')->get();

        return view('users.import', compact('courses'));
    }

    public function chunk(ImportUsersChunkRequest $request): JsonResponse
    {
        $orgId = $this->resolveOrgId($request);

        // Route-scope the course to the resolved org context (OrgScope
        // applies to `Course`), rather than trusting `course_id` alone.
        $course = Course::query()->findOrFail($request->validated('course_id'));

        $result = $this->importService->importChunk(
            rows: $request->validated('rows'),
            courseId: $course->id,
            orgId: $orgId,
            fileName: $request->validated('filename'),
        );

        return response()->json($result);
    }

    /**
     * @see ResolvesOrgContext::resolveOrgId()
     */
    protected function orgContextAction(): string
    {
        return 'importar usuários';
    }
}
