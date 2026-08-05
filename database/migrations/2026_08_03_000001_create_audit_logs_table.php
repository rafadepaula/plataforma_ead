<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-15 — `audit_logs` persists every auditable event (auth,
     * mutation, critical action). `org_id`/`user_id` are intentionally
     * nullable with `nullOnDelete()` (not `cascadeOnDelete()`): the audit
     * trail must survive the deletion of the Organization or User it
     * references, and many events (guest `login.failed`, Admin-global
     * actions with no active Organization) legitimately have a null
     * `org_id` from the moment they're written — see the
     * `audit-logs-architecture` skill for the `OrgScope`
     * creating-hook-bypass rationale.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event', 50)->index();
            $table->string('auditable_type')->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
