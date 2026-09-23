<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The video auto-completion threshold becomes a per-lesson setting:
     * `video_completion_percentage` (10–100, on the familiar 0–100 scale)
     * replaces the hardcoded 90%. `NULL` keeps the legacy 90% behaviour —
     * both for rows predating this migration and for lessons whose form
     * never carried the field (non-video lessons) — so no existing lesson
     * changes behaviour. The default backfills new rows with 90 as a
     * convenience for authoring UIs; the read path still tolerates NULL
     * (see `Lesson::effectiveVideoThreshold()`).
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->unsignedTinyInteger('video_completion_percentage')
                ->nullable()
                ->default(90)
                ->after('video_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('video_completion_percentage');
        });
    }
};
