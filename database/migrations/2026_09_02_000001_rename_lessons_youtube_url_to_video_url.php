<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generalizes the video column pair: `youtube_url` becomes the
     * provider-agnostic `video_url` and a sibling `video_provider`
     * (`youtube`|`vimeo`) records who owns it. `video_provider` is NULL
     * when a lesson carries no video at all; the default keeps every
     * pre-existing row (all YouTube by construction) classified. Stored
     * URLs are untouched — the YouTube sanitizer still parses the embed
     * form the normalization migration put them in.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->renameColumn('youtube_url', 'video_url');
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->enum('video_provider', ['youtube', 'vimeo'])->nullable()->default('youtube')->after('video_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('video_provider');
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->renameColumn('video_url', 'youtube_url');
        });
    }
};
