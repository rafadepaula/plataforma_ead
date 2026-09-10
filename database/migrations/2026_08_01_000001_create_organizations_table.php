<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `organizations` is the master tenant table.
     */
    public function up(): void
    {
        // `organizations` is the master tenant table. `host` is the tenant
        // key: the exact `HTTP_HOST` (lowercased, port stripped) that
        // resolves to this Organization. A host that matches nothing (or an
        // Organization without one) puts the app in "state zero" — the
        // admin-only login. `landing_view` names the Organization's own
        // landing blade under `resources/views/tenants/{landing_view}/`.
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('host')->nullable()->unique();
            $table->string('landing_view')->nullable();
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
