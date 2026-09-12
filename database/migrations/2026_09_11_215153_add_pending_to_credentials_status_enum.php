<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `pending` separates "created by the Gestor, never finalized by the
     * Aluno through their unique invitation link" from a Gestor-imposed
     * `inactive` deactivation: redeeming an invitation may activate a
     * `pending` account, but must never resurrect an `inactive` one.
     */
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table): void {
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('credentials')->where('status', 'pending')->update(['status' => 'inactive']);

        Schema::table('credentials', function (Blueprint $table): void {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
