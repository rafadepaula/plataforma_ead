<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-00 §2.1.21 — `system_settings` is directly org-scoped, but a
     * literal nullable `org_id` cannot participate in a composite
     * PRIMARY KEY on MySQL/MariaDB (PK columns are implicitly NOT NULL).
     *
     * Resolution (documented edge case — see `tenancy-maintenance` skill):
     * `org_id` uses a `0` sentinel for "global" settings instead of `NULL`,
     * keeping the composite PRIMARY KEY `(setting_key, org_id)` intact and
     * genuinely unique for both global and per-org rows. Because `0` is
     * not a real `organizations.id`, no database-level foreign key is
     * declared on `org_id` here — the same pattern already used for the
     * other pseudo-FK columns in this schema (`course_completion_rules.target_id`,
     * `forum_post_edits`/`forum_reports`'s `postable_id`). Referential
     * integrity for non-sentinel values is validated at the application
     * layer (`SystemSetting` model / service).
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key', 150);
            $table->unsignedBigInteger('org_id')->default(0);
            $table->text('setting_value')->nullable();
            $table->timestamps();

            $table->primary(['setting_key', 'org_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
