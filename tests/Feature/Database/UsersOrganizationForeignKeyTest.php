<?php

namespace Tests\Feature\Database;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * `users.org_id` uses `ON DELETE RESTRICT`, not
 * `CASCADE`. An Organization with existing users can be soft-deleted, but
 * a hard delete must fail at the database level while any user still
 * references it (see the `tenancy-maintenance` skill).
 */
class UsersOrganizationForeignKeyTest extends TestCase
{
    public function test_hard_deleting_an_organization_with_users_is_blocked_by_the_database(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->create(['org_id' => $organization->id]);

        $this->expectException(QueryException::class);

        $organization->forceDelete();
    }

    public function test_an_organization_with_no_users_can_be_soft_deleted(): void
    {
        $organization = Organization::factory()->create();

        $organization->delete();

        $this->assertSoftDeleted($organization);
    }

    public function test_an_organization_with_existing_users_can_still_be_soft_deleted(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->create(['org_id' => $organization->id]);

        $organization->delete();

        $this->assertSoftDeleted($organization);
    }
}
