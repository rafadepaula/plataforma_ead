<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-00 §4 — seeds the 3 fundamental, global (non-team-scoped) Spatie
 * roles backing `RolesEnum`.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolesEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
