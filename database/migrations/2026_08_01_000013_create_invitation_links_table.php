<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.13 — `invitation_links` is directly org-scoped
     * (`OrgScope` trait applies).
     */
    public function up(): void
    {
        Schema::create('invitation_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_id');
            $table->char('token', 64)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('current_uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index('org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_links');
    }
};
