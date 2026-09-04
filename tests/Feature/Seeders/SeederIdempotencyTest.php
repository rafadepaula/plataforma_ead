<?php

namespace Tests\Feature\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Validates that all database seeders are fully idempotent.
 * Re-running `db:seed` multiple times must never throw duplicate key exceptions
 * or produce duplicate records in the database.
 */
class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_on_consecutive_runs(): void
    {
        // First execution
        $this->artisan('db:seed')->assertExitCode(0);

        $roleCountInitial = Role::count();
        $userCountInitial = User::count();
        $settingCountInitial = SystemSetting::count();

        // Second execution on same database
        $this->artisan('db:seed')->assertExitCode(0);

        // Assert no duplicate rows created
        $this->assertSame($roleCountInitial, Role::count(), 'Role count must remain identical after second run.');
        $this->assertSame($userCountInitial, User::count(), 'User count must remain identical after second run.');
        $this->assertSame($settingCountInitial, SystemSetting::count(), 'SystemSetting count must remain identical after second run.');
    }

    public function test_individual_baseline_seeders_are_idempotent(): void
    {
        // Test RolesAndPermissionsSeeder idempotency
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder'])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder'])->assertExitCode(0);
        $this->assertSame(4, Role::count());

        // Test AdminSeeder idempotency
        $this->artisan('db:seed', ['--class' => 'AdminSeeder'])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => 'AdminSeeder'])->assertExitCode(0);
        $this->assertSame(1, User::where('email', 'admin@plataforma.com')->count());

        // Test SystemSettingSeeder idempotency
        $this->artisan('db:seed', ['--class' => 'SystemSettingSeeder'])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => 'SystemSettingSeeder'])->assertExitCode(0);
        $this->assertTrue(SystemSetting::query()->forKey('system_name')->forOrg(null)->exists());
    }
}
