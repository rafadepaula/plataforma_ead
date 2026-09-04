<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Video tracking moves from a single `watched_seconds` playhead figure
     * (inflated by forward seeks — the value was `GREATEST(playhead, stored)`,
     * never time actually watched) to the merged set of watched intervals.
     *
     * - `watched_ranges`: JSON of disjoint, sorted `[start, end)` second
     *   intervals (e.g. `[[0,120],[480,540]]`) — the union of every segment
     *   the client reported replayed.
     * - `watched_unique_seconds`: count of distinct seconds covered by those
     *   ranges; the only figure the 90% auto-completion threshold reads.
     * - `duration_seconds`: the video length as reported by the provider,
     *   persisted so the percentage can be recomputed if the video changes.
     *
     * The old `watched_seconds` column is dropped without backfill: it stored
     * the playhead, not watched time, so every existing value was inflated by
     * definition — there is no truthful range set to reconstruct from it.
     */
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->json('watched_ranges')->nullable()->after('completion_source');
            $table->unsignedInteger('watched_unique_seconds')->default(0)->after('watched_ranges');
            $table->unsignedInteger('duration_seconds')->nullable()->after('watched_unique_seconds');
            $table->dropColumn('watched_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->unsignedInteger('watched_seconds')->nullable()->after('completion_source');
            $table->dropColumn(['watched_ranges', 'watched_unique_seconds', 'duration_seconds']);
        });
    }
};
