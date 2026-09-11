<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `audience` is the minimum role required to see the article on the
     * public wiki (`aluno` = public tier, shared with guests; `admin` =
     * Admin-only). Existing articles default to the public tier.
     */
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table): void {
            $table->string('audience', 20)->default('aluno')->after('target_page_key');
            $table->index('audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table): void {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }
};
