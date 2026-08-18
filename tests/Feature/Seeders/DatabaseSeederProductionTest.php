<?php

namespace Tests\Feature\Seeders;

use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Validates that running DatabaseSeeder in production
 * environment seeds ONLY baseline data (Super Admin, Roles, System Settings)
 * and never populates test data or demo organizations.
 */
class DatabaseSeederProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->instance('env', 'testing');
        parent::tearDown();
    }

    public function test_production_seeding_executes_only_baseline_seeders(): void
    {
        // 1. Force production environment context
        $this->app->instance('env', 'production');
        $this->assertTrue(app()->environment('production'));

        // 2. Execute DatabaseSeeder
        $seeder = new DatabaseSeeder;
        $seeder->run();

        // 3. Assert Super Admin user exists and has admin role
        $adminEmail = config('app.admin_email', 'admin@plataforma.com');
        $admin = User::where('email', $adminEmail)->first();

        $this->assertNotNull($admin, 'Super Admin user should be created in production.');
        $this->assertTrue($admin->hasRole('admin'), 'Super Admin user must have the admin Spatie role.');

        // 4. Assert Spatie roles exist
        $this->assertTrue(Role::where('name', 'admin')->exists(), 'Role admin must exist.');
        $this->assertTrue(Role::where('name', 'gestor')->exists(), 'Role gestor must exist.');
        $this->assertTrue(Role::where('name', 'aluno')->exists(), 'Role aluno must exist.');

        // 5. Assert global system settings exist
        $this->assertTrue(
            SystemSetting::query()->forKey('system_name')->forOrg(null)->exists(),
            'Global system settings should be created in production.'
        );

        // 6. Assert NO test organizations or extraneous non-admin users were created
        $this->assertSame(0, Organization::count(), 'No organizations should be created in production.');
        $this->assertSame(1, User::count(), 'Only the Super Admin user should exist in production.');
    }
}
