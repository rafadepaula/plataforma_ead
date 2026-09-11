<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The per-organization account behind a `User` (the person). The login
 * identity is the `(user_id, org_id)` pair, where `org_id` is resolved from
 * the request host; `org_id = null` is the system-wide Admin account, valid
 * on every host (including "state zero"). `OrgScope` is intentionally NOT
 * applied: org context comes from the request host and every query is
 * explicit about the org it targets.
 */
class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use AuditableTrait, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'org_id',
        'password',
        'status',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'string',
        ];
    }

    /**
     * Kills the account's remember-me cookie: every password change and
     * every deactivation must rotate `remember_token`, otherwise a
     * "remembered" device survives the credential's security change (the
     * cookie is only validated against the stored value).
     */
    public function rotateRememberToken(): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /**
     * Restrict to the account of one Organization (`null` = the global
     * Admin account). Used everywhere the host-resolved org feeds the
     * credential lookup — login, password reset, membership screens.
     */
    public function scopeForOrg(Builder $query, ?int $orgId): Builder
    {
        return $orgId === null
            ? $query->whereNull('org_id')
            : $query->where('org_id', $orgId);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
