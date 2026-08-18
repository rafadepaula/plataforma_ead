<?php

namespace App\Models;

use App\Models\Traits\OrgScope;
use Database\Factories\InvitationLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class InvitationLink extends Model
{
    /** @use HasFactory<InvitationLinkFactory> */
    use HasFactory, OrgScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'token',
        'course_id',
        'max_uses',
        'current_uses',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'current_uses' => 'integer',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * a link past its `expires_at` may no longer be
     * consumed, even if it still has uses remaining.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * a link with a `max_uses` cap that has been reached may
     * no longer be consumed. `max_uses = null` means unlimited uses.
     */
    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->current_uses >= $this->max_uses;
    }

    /**
     * Link-level revocation (distinct from `course_user.status =
     * 'cancelled'`, the per-enrollment revocation used by RF21) — set by
     * `InvitationLinkController::destroy()`.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * A single source of truth for "may this link still be consumed?",
     * used both by {@see self::scopeUsable()} (public `/convite/{token}`
     * lookup) and re-checked inside `ProcessSmartInvitationAction`'s
     * `lockForUpdate` transaction, since the pre-lock scope check alone
     * cannot prevent two concurrent requests from both passing it at
     * exactly `max_uses`.
     */
    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted() && ! $this->isRevoked() && $this->courseIsAvailable();
    }

    /**
     * The linked `Course` must still exist (not soft-deleted — `belongsTo`
     * already excludes trashed rows by default) and be published; a link
     * pointing at an unpublished or soft-deleted Course has nothing to
     * enroll the invitee into and is therefore just as unusable as an
     * expired/exhausted/revoked one.
     *
     * Reuses an already-hydrated `course` relation when present (e.g. set
     * by `InvitationLinkController::index()` from the parent Course it
     * already loaded) instead of always issuing a fresh query — callers
     * without a pre-loaded relation (the public `/convite/{token}` lookup)
     * still fall back to the `withoutGlobalScope` query untouched.
     */
    public function courseIsAvailable(): bool
    {
        if ($this->relationLoaded('course')) {
            return (bool) $this->course?->is_published;
        }

        return (bool) $this->course()->withoutGlobalScope('org')->value('is_published');
    }

    /**
     * @param  Builder<InvitationLink>  $query
     * @return Builder<InvitationLink>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('current_uses', '<', 'max_uses'));
    }
}
