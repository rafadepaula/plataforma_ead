<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 *  data migration: moves every lesson PDF from the `public` disk
 * (`storage/app/public/...`, reachable via `public/storage`) to the `local`
 * disk (`storage/app/private/...`, never symlinked) at the SAME relative
 * path, so the gated `lessons.pdf.show` route becomes the only read path.
 *
 * Covers both `lesson_media` rows of kind=pdf and the deprecated flat
 * `lessons.pdf_path` column (same file may be referenced by both — the
 * second pass is then a no-op via the `local`-exists check).
 *
 * Safety contract (precedent: `2026_08_13_000001_normalize_lesson_youtube_urls.php`):
 * - Idempotent: a path already present on `local` is skipped, so re-running
 *   is a no-op.
 * - Never destructive: a path missing on BOTH disks is silently skipped
 *   rather than nulled — the classroom renders its neutral
 *   "Documento indisponível" notice for it.
 * - Reads via the query builder (not Eloquent), so soft-deleted lessons and
 *   any global scope are irrelevant: every physical row is considered.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_media')
            ->where('kind', 'pdf')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $this->movePdf((string) ($row->path ?? ''));
                }
            });

        DB::table('lessons')
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($lessons): void {
                foreach ($lessons as $lesson) {
                    $this->movePdf((string) $lesson->pdf_path);
                }
            });
    }

    private function movePdf(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            return;
        }

        if (! Storage::disk('public')->exists($path)) {
            return;
        }

        Storage::disk('local')->put($path, (string) Storage::disk('public')->get($path));
        Storage::disk('public')->delete($path);
    }

    /**
     * Irreversible by design: the `public`-disk bytes are deleted on move,
     * and the relative paths are unchanged, so there is nothing meaningful
     * to roll back to.
     */
    public function down(): void
    {
        // No-op.
    }
};
