<?php

namespace Tests;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Neutral host used for state-zero (admin-only) feature requests.
     * Real DNS is irrelevant here: the request host is taken from the URI
     * and resolved purely against `organizations.host`.
     */
    public const string ADMIN_HOST = 'localhost.admin';

    protected ?string $testHost = null;

    /**
     * Pin the Organization host every subsequent request in this test hits
     * (`HTTP_HOST`). The host is carried in the request URI because modern
     * Symfony hard-derives `HTTP_HOST`/`SERVER_NAME` from the URI and
     * ignores a spoofed server variable. `null` restores the app.url
     * default (state zero on `localhost`).
     */
    protected function onHost(?string $host): static
    {
        $this->testHost = $host;

        return $this;
    }

    protected function prepareUrlForRequest($uri)
    {
        if ($this->testHost !== null && str_starts_with($uri, '/')) {
            $uri = 'http://'.$this->testHost.$uri;
        }

        return parent::prepareUrlForRequest($uri);
    }

    /**
     * Log in as a system Admin (global account: `credentials.org_id = null`,
     * `admin` role) on the neutral state-zero host.
     *
     * Pass an Organization to also simulate an active "Impersonate Org"
     * context — by seeding `session('active_org_id')`, which overrides the
     * request host for Admins only.
     */
    protected function actingAsAdmin(?Organization $impersonatedOrg = null): User
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(RolesEnum::ADMIN->value);
        Credential::factory()->create(['user_id' => $admin->id, 'org_id' => null]);

        $this->actingAs($admin);
        $this->onHost(self::ADMIN_HOST);

        if ($impersonatedOrg) {
            $this->withSession(['active_org_id' => $impersonatedOrg->id]);
        }

        return $admin;
    }

    /**
     * Log in as an org-bound user (`gestor` role by default) holding the
     * Organization account of the given (or freshly created) Organization,
     * on that Organization's host — the same way the real portal works.
     */
    protected function actingAsOrgUser(
        ?Organization $organization = null,
        string $role = 'gestor',
    ): User {
        $organization ??= Organization::factory()->create();

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($role);
        Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

        $this->actingAs($user);
        $this->onHost($organization->host);

        return $user;
    }
}
