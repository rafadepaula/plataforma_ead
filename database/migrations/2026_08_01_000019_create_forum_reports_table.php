<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.19 — `forum_reports` is the moderation/denunciation
     * queue (see SPEC-10 §2.2). `postable_type`/`postable_id` are a
     * pseudo-polymorphic pair with intentionally NO database foreign
     * key — integrity is validated at the application layer only.
     */
    public function up(): void
    {
        Schema::create('forum_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('postable_type', 50);
            $table->unsignedBigInteger('postable_id');
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'reviewed_dismissed', 'reviewed_removed'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['postable_type', 'postable_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_reports');
    }
};
