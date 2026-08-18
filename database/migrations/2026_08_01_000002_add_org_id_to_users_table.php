<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * alters the pre-existing base `users` table (added by
     * `0001_01_01_000000_create_users_table.php`, intentionally left
     * untouched) with the multitenancy/domain columns. `admin` rows carry
     * `org_id = null`; `gestor` rows always have `org_id` set; `aluno` rows
     * may be `org_id = null` since they enroll per-course via `course_user`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('org_id')->nullable()->after('id');
            $table->string('cpf', 14)->nullable()->unique()->after('email');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('password');

            $table->foreign('org_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->index('org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['org_id']);
            $table->dropIndex(['org_id']);
            $table->dropColumn(['org_id', 'cpf', 'status']);
        });
    }
};
