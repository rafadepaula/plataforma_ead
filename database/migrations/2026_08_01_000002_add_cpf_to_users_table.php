<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * adds the personal-identity column to the base `users` table (added by
     * `0001_01_01_000000_create_users_table.php`). Since the host-based
     * tenancy shift, `users` carries no organization context and no account
     * credentials: per-org accounts (password/status/remember token) live in
     * `credentials`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cpf', 14)->nullable()->unique()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('cpf');
        });
    }
};
