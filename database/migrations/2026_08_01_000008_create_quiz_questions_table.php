<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.8 — `quiz_questions` cascade-inherited from `quizzes`.
     */
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('type', ['single_choice', 'multiple_choice', 'true_false', 'essay'])->default('single_choice');
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['quiz_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
