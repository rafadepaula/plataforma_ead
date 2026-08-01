<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.3 — `courses` is directly org-scoped (`OrgScope` trait
     * applies). Deletion guard against active enrollments is enforced at
     * the application layer (Course model / controller), not here.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('workload_hours')->default(0);
            $table->boolean('is_published')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->index('org_id');
            $table->index(['org_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
