<?php

namespace App\Models;

use Database\Factories\ForumPostEditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public edit-history log for `ForumTopic`/`ForumReply` (SPEC-10 §2.2).
 * `postable_type`/`postable_id` are a pseudo-polymorphic pair with no real
 * database foreign key — integrity is validated at the application layer.
 * Do NOT apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class ForumPostEdit extends Model
{
    /** @use HasFactory<ForumPostEditFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'postable_type',
        'postable_id',
        'editor_user_id',
        'previous_content',
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
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_user_id');
    }
}
