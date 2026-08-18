<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `quizzes` is cascade-inherited (org implied by
     * `lessons` -> `modules` -> `courses.org_id`). One quiz per lesson.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained('lessons')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->boolean('allow_retries')->default(true);
            $table->unsignedTinyInteger('max_attempts')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('show_correct_answers')->default(false);
            $table->unsignedTinyInteger('min_score_percentage')->default(70);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
