<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `certificates` is cascade-inherited (org implied by
     * `course_id`/`user_id`). Revocation is logical (`revoked_at`,
     * `revoke_reason`), never a soft-delete of the row: the validation hash
     * must keep resolving publicly with a "Revogado" status.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->char('validation_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();

            $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
