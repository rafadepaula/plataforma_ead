<?php

use App\Services\YoutubeSanitizerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUG-002 — data migration: normalizes every legacy `lessons.youtube_url`
 * written before `44c7e8a` (when `CourseSeeder` and any direct `UPDATE`/import
 * bypassed `YoutubeSanitizerService`) to the canonical, embeddable
 * `https://www.youtube.com/embed/{id}` form.
 *
 * Safety contract:
 * - Idempotent: rows already in canonical form produce an identical value and
 *   are skipped, so re-running is a no-op.
 * - Never destructive: `NULL`, empty, and unrecognizable values (a Vimeo link,
 *   a typo, a `javascript:` URI) are left exactly as they are rather than
 *   being nulled out — a human must decide what those rows should become.
 * - Reads via the query builder (not Eloquent), so soft-deleted lessons and
 *   any global scope are irrelevant: every physical row is considered.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = app(YoutubeSanitizerService::class);

        DB::table('lessons')
            ->whereNotNull('youtube_url')
            ->where('youtube_url', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($lessons) use ($sanitizer): void {
                foreach ($lessons as $lesson) {
                    $canonical = $sanitizer->tryCanonicalize($lesson->youtube_url);

                    if ($canonical === null || $canonical === $lesson->youtube_url) {
                        continue;
                    }

                    DB::table('lessons')
                        ->where('id', $lesson->id)
                        ->update(['youtube_url' => $canonical]);
                }
            });
    }

    /**
     * Irreversible by design: the original, non-canonical strings are not
     * retained anywhere, and the canonical form is a strict superset of what
     * every consumer accepts, so there is nothing meaningful to roll back to.
     */
    public function down(): void
    {
        // No-op.
    }
};
