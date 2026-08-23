<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `lesson_media` holds the MULTIPLE images/PDFs a lesson may carry
     * (the legacy `lessons.image_path`/`pdf_path` VARCHAR columns only ever
     * fit one of each). Cascade-inherited: org is implied by
     * `lessons` -> `modules` -> `courses.org_id`, so there is no `org_id`
     * here and no `OrgScope` on the model — see `tenancy-architecture`.
     */
    public function up(): void
    {
        Schema::create('lesson_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->enum('kind', ['image', 'pdf']);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_media');
    }
};
