<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.4 — `course_user` is the multi-org enrollment pivot.
     * Intentionally NOT org-scoped: it is how a student enrolls across
     * multiple Organizations.
     */
    public function up(): void
    {
        Schema::create('course_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamp('enrolled_at');
            $table->enum('status', ['active', 'cancelled', 'completed'])->default('active');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_user');
    }
};
