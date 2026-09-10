<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the global Super Admin: a person whose single
 * `credentials` row has `org_id = null` — the only account valid on every
 * host, including "state zero" (unmapped host / direct IP).
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
                'email_verified_at' => now(),
            ]
        );

        Credential::firstOrCreate(
            ['user_id' => $admin->id, 'org_id' => null],
            [
                'password' => Hash::make($password),
                'status' => 'active',
                'remember_token' => Str::random(60),
            ]
        );

        if (! $admin->hasRole(RolesEnum::ADMIN->value)) {
            $admin->assignRole(RolesEnum::ADMIN->value);
        }
    }
}
