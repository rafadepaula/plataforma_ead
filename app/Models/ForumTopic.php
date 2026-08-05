<?php

namespace App\Models;

use App\Models\Traits\OrgScope;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory, OrgScope, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'course_id',
        'user_id',
        'title',
        'content',
        'is_pinned',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * SPEC-10 §2 — pinned topics surface first in the per-course topic
     * list, most-recent-first within each group.
     *
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ForumReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id');
    }
}
