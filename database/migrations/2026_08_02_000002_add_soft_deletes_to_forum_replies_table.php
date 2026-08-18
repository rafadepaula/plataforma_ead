<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "apagar" a reply is a logical removal-from-display
     * that still preserves the pre-delete content in `forum_post_edits`,
     * so `deleted_at` is added here rather than to the original creation
     * migration (already migrated).
     */
    public function up(): void
    {
        Schema::table('forum_replies', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forum_replies', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
