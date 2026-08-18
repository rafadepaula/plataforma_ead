<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `course_completion_rules` cascade-inherited from
     * `courses`. `target_id` is a pseudo-polymorphic pointer (to
     * `modules.id` or `quizzes.id` depending on `rule_type`) with
     * intentionally NO database foreign key — integrity is validated at
     * the application layer only (see `tenancy-conventions` skill).
     */
    public function up(): void
    {
        Schema::create('course_completion_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('rule_type', ['all_lessons', 'min_quiz_score', 'specific_module'])->default('all_lessons');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedTinyInteger('required_percentage')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_completion_rules');
    }
};
