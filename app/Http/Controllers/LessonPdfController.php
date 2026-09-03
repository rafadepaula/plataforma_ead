<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 *  gated, inline stream of a lesson's PDF attachment.
 *
 * Reached as `GET lessons/{lesson}/pdf/{index}` behind `auth` +
 * `student.enrolled` (see `routes/web.php`): enrollment/tenancy is the
 * middleware's job (`EnsureStudentIsEnrolled` — Aluno without an active
 * enrollment is redirected, cross-org Gestor gets 403, guests hit login).
 * This action only answers what the middleware cannot know: draft
 * visibility, index bounds and file presence.
 *
 * The bytes are served from the `local` disk with `Content-Disposition:
 * inline` + `Cache-Control: private, no-store` — never `download()` (that
 * would re-add a save dialog) and never `temporaryUrl()`/signed links (a
 * shareable URL is a download vector). The PdfViewer frontend fetches this
 * same-origin with `X-Requested-With` and renders the bytes into `<canvas>`
 * via pdf.js, so no document URL ever exists in the DOM.
 */
class LessonPdfController extends Controller
{
    public function show(Request $request, Lesson $lesson, int $index): StreamedResponse
    {
        $user = $request->user();

        abort_unless($lesson->is_published || ! $user->hasRole(RolesEnum::ALUNO->value), 404);

        $attachment = $lesson->pdfAttachments()->get($index);

        abort_unless($attachment !== null, 404);

        $path = (string) $attachment->path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        $originalName = (string) ($attachment->original_name ?: basename($path));

        return Storage::disk('local')->response($path, $originalName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$originalName.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
