<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `lessons` is cascade-inherited (org implied by
     * `modules` -> `courses.org_id`).
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title', 200);
            $table->enum('type', ['content', 'quiz'])->default('content');
            $table->longText('content_text')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_published')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['module_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
