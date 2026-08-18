<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 *   &  §2.2 — seeds the global Super Admin user
 * with `role:admin` and configurable credentials from environment.
 */
class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('app.admin_email', 'admin@plataforma.com');
        $password = config('app.admin_password', 'admin');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'status' => 'active',
                'org_id' => null,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole(RolesEnum::ADMIN->value)) {
            $admin->assignRole(RolesEnum::ADMIN->value);
        }
    }
}
