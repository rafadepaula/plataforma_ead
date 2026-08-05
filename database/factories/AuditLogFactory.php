<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 *
 * `org_id`/`user_id` are intentionally left out of the default definition
 * (mirrors `InvitationLinkFactory`'s convention): callers set them
 * explicitly via `->for(...)` or `->create([...])`. Rows created through
 * this factory bypass `AuditService`/`OrgScope`'s `creating` hook
 * entirely — the factory's `AuditLog::create()` call never invokes
 * `withoutEvents()` on its own, so callers touching a non-null `org_id`
 * from a Gestor-scoped test context should use `AuditLog::withoutEvents()`
 * around `->create()` just like `HelpArticleFactory` usages do, to avoid
 * an unrelated `org_id` mismatch from `OrgScope`'s `creating` hook.
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'user.updated',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'url' => $this->faker->url(),
        ];
    }

    /**
     * Shape a `login.success`/`login.failed` style event.
     */
    public function login(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'login.success',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    /**
     * Shape a generic "Mutação Geral" `{model}.updated` style event, with
     * an old/new value diff payload.
     */
    public function mutation(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'course.updated',
            'auditable_type' => 'course',
            'auditable_id' => $this->faker->numberBetween(1, 1000),
            'old_values' => ['title' => 'Old title'],
            'new_values' => ['title' => 'New title'],
        ]);
    }
}
