<?php

namespace Tests\Unit\Seeders;

use App\Enums\Permissions\RolesEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * seeds exactly the 4 fundamental, global (non-team-scoped)
 * roles backing `RolesEnum`.
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    public function test_it_creates_exactly_the_four_fundamental_roles(): void
    {
        // The `create_permission_tables` migration already seeds these
        // roles as part of `RefreshDatabase`; assert the seeder is
        // idempotent (`findOrCreate`) and the end result is still exactly
        // the 4 fundamental roles.
        (new RolesAndPermissionsSeeder)->run();

        $roleNames = Role::query()->pluck('name')->sort()->values()->all();

        $this->assertSame(['admin', 'aluno', 'gestor', 'professor'], $roleNames);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_roles(): void
    {
        (new RolesAndPermissionsSeeder)->run();
        (new RolesAndPermissionsSeeder)->run();

        $this->assertSame(4, Role::query()->count());
    }

    public function test_every_rolesenum_case_has_a_matching_seeded_role(): void
    {
        (new RolesAndPermissionsSeeder)->run();

        foreach (RolesEnum::cases() as $role) {
            $this->assertTrue(Role::query()->where('name', $role->value)->exists());
        }
    }
}
