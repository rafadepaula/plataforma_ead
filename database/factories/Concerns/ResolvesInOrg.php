<?php

namespace Database\Factories\Concerns;

use App\Models\Organization;

/**
 * Shared resolution for the `->inOrg(...)` factory shorthand: callers may
 * pass the Organization itself, its id (common when flowing `$model->org_id`
 * through test setup), or `null` — which means the global (admin) context.
 */
trait ResolvesInOrg
{
    protected static function resolveInOrg(Organization|int|null $organization): ?Organization
    {
        if (is_int($organization)) {
            return Organization::findOrFail($organization);
        }

        return $organization;
    }
}
