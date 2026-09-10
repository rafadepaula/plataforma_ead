<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `credentials` is the per-organization account: the pair
     * `(user_id, org_id)` is the login identity, where `org_id` comes from
     * the request host. A row with `org_id = null` is the system-wide Admin
     * account, valid on every host (including "state zero"). Passwords are
     * intentionally per-organization: the same person may hold one
     * credential per Organization, each with its own password and
     * active/inactive status.
     *
     * The composite unique index keeps one account per (user, org) pair.
     * MySQL permits multiple NULL `org_id` rows in a unique index, so the
     * single-Admin-account rule is enforced application-side (one
     * `org_id = null` credential per user).
     */
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('org_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['user_id', 'org_id']);
            $table->index('org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
