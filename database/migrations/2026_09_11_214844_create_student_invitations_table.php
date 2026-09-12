<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `student_invitations` is directly org-scoped (`OrgScope` trait
     * applies). Unlike the shareable `invitation_links` it replaces, each
     * row is bound to one pre-registered `users` row: the Gestor creates
     * the Aluno first, and the token only finalizes that person's own
     * account (set password + activate the org credential). `created_by`
     * is `ON DELETE SET NULL` on purpose — removing the Gestor who issued
     * the invitation must never block a user deletion.
     */
    public function up(): void
    {
        Schema::create('student_invitations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_id');
            $table->char('token', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
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
        Schema::dropIfExists('student_invitations');
    }
};
