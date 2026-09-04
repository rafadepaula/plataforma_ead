<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Retomar de onde parou" means the PLAYHEAD, not the first unwatched
     * second: a student who seeks from 0:20 to 0:50 and reloads the page
     * must land back on 0:50 — even though second 50 was never watched.
     * The client reports its current position on every progress POST and
     * the latest value is stored here (the ranges stay the watched-time
     * authority; this column is only the resume bookmark).
     */
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->unsignedInteger('last_position_seconds')->nullable()->after('duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->dropColumn('last_position_seconds');
        });
    }
};
