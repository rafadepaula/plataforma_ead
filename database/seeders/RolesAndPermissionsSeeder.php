<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-00 §4 & SPEC-16 §2.2 — seeds the 3 fundamental Spatie roles
 * (`admin`, `gestor`, `aluno`) and default permissions for the platform.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Default platform permissions seeded baseline.
     *
     * @var list<string>
     */
    protected array $permissions = [
        'manage organizations',
        'manage users',
        'manage courses',
        'view courses',
        'access settings',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Seed Spatie Roles (idempotent firstOrCreate)
        foreach (RolesEnum::cases() as $role) {
            Role::firstOrCreate(
                ['name' => $role->value, 'guard_name' => 'web'],
                ['name' => $role->value, 'guard_name' => 'web']
            );
        }

        // 2. Seed Default Permissions (idempotent firstOrCreate)
        foreach ($this->permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }
    }
}
