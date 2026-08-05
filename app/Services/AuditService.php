<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SPEC-15 — single write path for the audit trail. Every call dual-writes:
 * the `audit_logs` table (best-effort, wrapped in try/catch so a DB
 * failure never blocks the primary request — RN "duplo armazenamento")
 * and the dedicated `audit` Monolog channel (always attempted,
 * independent of the DB outcome).
 *
 * The DB write goes through `AuditLog::withoutEvents()` to bypass
 * `OrgScope`'s `creating` hook — see the `audit-logs-architecture` skill.
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $payload  extra context merged into the Monolog entry
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function log(
        string $event,
        ?int $orgId = null,
        ?int $userId = null,
        array $payload = [],
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        // In a pure Artisan/console context (`audit-logs:prune`, `artisan
        // tinker`) `request()` still resolves to a captured `Request`
        // instance, but with no server variables populated — `ip()`/
        // `userAgent()`/`fullUrl()` degrade to null/empty gracefully
        // rather than throwing.
        $request = request();
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();
        $url = $request?->fullUrl();

        // `$payload` is the caller's free-form context (e.g. `old_status`/
        // `new_status`, `total_processed`, `revocation_reason`). It must
        // land in the DB row too — not just the Monolog line — or the
        // RF33 diff-modal/CSV export has nothing to show for critical
        // actions that don't carry explicit `oldValues`/`newValues`.
        $dbNewValues = empty($payload) ? $newValues : array_merge($newValues ?? [], $payload);

        $attributes = [
            'org_id' => $orgId,
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $dbNewValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $url,
        ];

        try {
            AuditLog::withoutEvents(fn () => AuditLog::query()->create($attributes));
        } catch (Throwable $e) {
            report($e);
        }

        try {
            Log::channel('audit')->info($event, array_merge($attributes, ['new_values' => $newValues], $payload));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
