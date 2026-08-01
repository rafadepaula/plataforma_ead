<?php

namespace App\Models;

use Database\Factories\ForumReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cascade-inherited: org is implied by `topic.org_id`. Do NOT apply
 * `OrgScope` here — see the `tenancy-architecture` skill.
 */
class ForumReply extends Model
{
    /** @use HasFactory<ForumReplyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'topic_id',
        'user_id',
        'content',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
