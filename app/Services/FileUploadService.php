<?php

namespace App\Services;

use App\Exceptions\UnresolvedOrgContextException;
use App\Models\Course;
use Illuminate\Http\UploadedFile;

/**
 * SPEC-05 §3 — stores Lesson media (cover images, PDFs) on the `public`
 * disk under a per-tenant, per-course isolated path:
 * `storage/app/public/orgs/{org_id}/courses/{course_id}/{images|pdfs}/...`.
 *
 * The tenant is resolved primarily from the given `Course` model's own
 * `org_id` (never from the currently logged-in Gestor alone) so an Admin
 * impersonating a different Organization than the Course's owner never
 * writes a file to a mismatched tenant folder. `Course::org_id` is always
 * populated by `OrgScope`, so the `auth()`/session fallback only matters
 * for edge cases where a Course instance is built without going through
 * that hook (e.g. directly in a factory/test).
 */
class FileUploadService
{
    public function storeImage(UploadedFile $file, Course $course): string
    {
        return $this->store($file, $course, 'images');
    }

    public function storePdf(UploadedFile $file, Course $course): string
    {
        return $this->store($file, $course, 'pdfs');
    }

    protected function store(UploadedFile $file, Course $course, string $kind): string
    {
        $orgId = $this->resolveOrgId($course);

        $path = "orgs/{$orgId}/courses/{$course->id}/{$kind}";

        return $file->store($path, 'public');
    }

    protected function resolveOrgId(Course $course): int
    {
        $orgId = $course->org_id ?? auth()->user()?->org_id ?? session('active_org_id');

        if (! $orgId) {
            throw new UnresolvedOrgContextException(
                'Não foi possível resolver org_id para upload de arquivo do Curso #'.($course->id ?? 'novo').'.'
            );
        }

        return (int) $orgId;
    }
}
