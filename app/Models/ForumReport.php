<?php

namespace App\Models;

use Database\Factories\ForumReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moderation/denunciation queue for `ForumTopic`/`ForumReply` (SPEC-10
 * §2.2). `postable_type`/`postable_id` are a pseudo-polymorphic pair with
 * no real database foreign key — integrity is validated at the
 * application layer. Do NOT apply `OrgScope` here — see the
 * `tenancy-architecture` skill.
 */
class ForumReport extends Model
{
    /** @use HasFactory<ForumReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'postable_type',
        'postable_id',
        'reported_by',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Resolves the `ForumTopic|ForumReply` this report targets.
     * `postable_type`/`postable_id` carry no DB FK/morphTo, so this is
     * the single place both are resolved — kept in sync with
     * `ForumPostEdit::postable()`. `withTrashed()` is required: the
     * reported post may already be soft-deleted by direct gestor/admin
     * moderation before the report queue is reviewed (see the plan's
     * edge cases) — the moderation UI must not crash resolving it.
     */
    public function postable(): ForumTopic|ForumReply|null
    {
        /** @var class-string<ForumTopic|ForumReply> $type */
        $type = $this->postable_type;

        return $type::withTrashed()->find($this->postable_id);
    }
}
