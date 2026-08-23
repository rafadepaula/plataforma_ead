<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 *  data migration: copies every non-null legacy
 * `lessons.image_path`/`pdf_path` into `lesson_media` rows so pre-existing
 * single-file lessons appear in the new multi-attachment list and in the
 * student classroom without duplicating (views only fall back to the legacy
 * column when the media relation is empty).
 *
 * Safety contract:
 * - Idempotent: a lesson whose legacy path already has a matching
 *   `lesson_media` row (by kind + path) is skipped, so re-running is a no-op.
 * - Non-destructive: the legacy columns are kept (compat with existing read
 *   paths); they are only dropped once every consumer reads `Lesson::media()`.
 * - Reads via the query builder (not Eloquent), so soft-deleted lessons and
 *   global scopes are irrelevant: every physical row is considered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_media')) {
            return;
        }

        $this->backfillKind('image_path', 'image');
        $this->backfillKind('pdf_path', 'pdf');
    }

    /**
     * Copies non-null/non-empty values of a legacy column into `lesson_media`
     * rows of the matching kind, skipping lessons that already carry the
     * same path for that kind.
     */
    private function backfillKind(string $legacyColumn, string $kind): void
    {
        $now = now();

        DB::table('lessons')
            ->whereNotNull($legacyColumn)
            ->where($legacyColumn, '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($lessons) use ($legacyColumn, $kind, $now): void {
                foreach ($lessons as $lesson) {
                    $alreadyBackfilled = DB::table('lesson_media')
                        ->where('lesson_id', $lesson->id)
                        ->where('kind', $kind)
                        ->where('path', $lesson->{$legacyColumn})
                        ->exists();

                    if ($alreadyBackfilled) {
                        continue;
                    }

                    DB::table('lesson_media')->insert([
                        'lesson_id' => $lesson->id,
                        'kind' => $kind,
                        'path' => $lesson->{$legacyColumn},
                        'original_name' => basename($lesson->{$legacyColumn}),
                        'size_bytes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * Irreversible by design: backfilled rows are indistinguishable from
     * rows created through the new upload flow, and the legacy columns are
     * retained, so there is nothing meaningful to roll back to.
     */
    public function down(): void
    {
        // No-op.
    }
};
