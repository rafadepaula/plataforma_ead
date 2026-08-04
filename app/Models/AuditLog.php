<?php

namespace App\Models;

use App\Models\Traits\OrgScope;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC-15 — audit trail row. Uses `OrgScope` for its SELECT-side global
 * scope only (Gestor restricted to own `org_id`, Admin sees all/
 * impersonated org on `index()`/queries) — every write MUST go through
 * `AuditService::log()`, which wraps the insert in `AuditLog::withoutEvents()`
 * to bypass `OrgScope`'s `creating` hook. That hook auto-resolves and
 * throws `UnresolvedOrgContextException` when no tenant can be resolved,
 * which is wrong here: many audit events (guest `login.failed`,
 * Admin-global actions) legitimately have a null `org_id`. See the
 * `audit-logs-architecture` skill for the full rationale.
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, OrgScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
