<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `forum_post_edits` is the public edit-history log
     * . `postable_type`/`postable_id` are a
     * pseudo-polymorphic pair (`forum_topic` or `forum_reply`) with
     * intentionally NO database foreign key — integrity is validated at
     * the application layer only.
     */
    public function up(): void
    {
        Schema::create('forum_post_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('postable_type', 50);
            $table->unsignedBigInteger('postable_id');
            $table->foreignId('editor_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('previous_content');
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->index(['postable_type', 'postable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_post_edits');
    }
};
