<?php

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cascade-inherited: org is implied by `quiz.lesson.module.course.org_id`.
 * Do NOT apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'user_id',
        'score_percentage',
        'is_passed',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * Keeps `open_slot` in sync with `status` so the
     * `quiz_attempts_open_slot_unique` index enforces "at most one open
     * attempt per quiz and student" no matter which code path writes the
     * row. The column is never mass-assignable — `status` alone drives it.
     */
    protected static function booted(): void
    {
        static::saving(function (QuizAttempt $attempt): void {
            $attempt->open_slot = $attempt->status === 'in_progress' ? 1 : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score_percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QuizAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}
