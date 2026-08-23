<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `expires_at` is the enrollment's optional access deadline.
     * `null` means "never expires" (the default for every pre-existing
     * row and every enrollment created without one) — no backfill is
     * needed. The existing 3-value `status` enum is left untouched: an
     * "expired" enrollment is derived, not stored, as
     * `status = active AND expires_at` is in the past (see
     * `Course::enrollmentDisplayStatusFor()`).
     */
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
