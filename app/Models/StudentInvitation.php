<?php

namespace App\Models;

use App\Exceptions\InvitationInvalidException;
use App\Models\Traits\OrgScope;
use Database\Factories\StudentInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StudentInvitation extends Model
{
    /** @use HasFactory<StudentInvitationFactory> */
    use HasFactory, OrgScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'token',
        'user_id',
        'created_by',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * The pre-registered Aluno this invitation finalizes the account for —
     * the e-mail/name shown (immutable) on the public form come from here,
     * never from the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * an invitation past its `expires_at` may no longer be redeemed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * an invitation is single-use: once redeemed
     * (`used_at` set) the token can never finalize anything again.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Invitation-level revocation, set by the Gestor's
     * "renovar"/revoke actions so a leaked link stops working.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * A single source of truth for "may this invitation still be
     * redeemed?", re-checked inside `RedeemStudentInvitationAction`'s
     * `lockForUpdate` transaction — the pre-lock check alone cannot
     * prevent two concurrent requests from both passing it.
     */
    public function isUsable(): bool
    {
        return $this->unusableReason() === null;
    }

    /**
     * Why this invitation may no longer be redeemed, as one of
     * {@see InvitationInvalidException}'s `REASON_*` constants, or `null`
     * when it is still usable.
     *
     * The order is a deliberate, deterministic precedence — revoked >
     * expired > used — because a single row can sit in several of those
     * states at once and the visitor must always be told the same reason
     * for the same row.
     */
    public function unusableReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => InvitationInvalidException::REASON_REVOKED,
            $this->isExpired() => InvitationInvalidException::REASON_EXPIRED,
            $this->isUsed() => InvitationInvalidException::REASON_USED,
            default => null,
        };
    }

    /**
     * @param  Builder<StudentInvitation>  $query
     * @return Builder<StudentInvitation>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->whereNull('used_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()));
    }
}
