<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `help_articles` is directly org-scoped, but
     * `org_id` is nullable: a `null` article is global (visible to every
     * Organization), while a non-null one is org-specific.
     */
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('category', 100)->nullable();
            $table->string('target_page_key', 150)->nullable();
            $table->longText('content');
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index('org_id');
            $table->index('target_page_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
