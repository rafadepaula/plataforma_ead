<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `course_professor` is the explicit course-to-teacher assignment
     * pivot. Mirrors `course_user`'s tenancy design: intentionally NOT
     * org-scoped — the tenant boundary comes from `courses.org_id`, and
     * the existence of the pivot row IS the access boundary (`User::teaches()`).
     * `UNIQUE(course_id, user_id)` makes reassignment idempotent.
     */
    public function up(): void
    {
        Schema::create('course_professor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_professor');
    }
};
