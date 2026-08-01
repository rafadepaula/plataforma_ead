<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cascade-inherited: org is implied by `lesson.module.course.org_id`. Do
 * NOT apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'title',
        'instructions',
        'allow_retries',
        'max_attempts',
        'time_limit_minutes',
        'show_correct_answers',
        'min_score_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_retries' => 'boolean',
            'max_attempts' => 'integer',
            'time_limit_minutes' => 'integer',
            'show_correct_answers' => 'boolean',
            'min_score_percentage' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
