<?php

namespace App\Models;

use App\Services\VideoWatchCalculator;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cascade-inherited: org is implied by `lesson.module.course.org_id`. Do
 * NOT apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected $table = 'lesson_progress';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'lesson_id',
        'is_completed',
        'completion_source',
        'watched_ranges',
        'watched_unique_seconds',
        'duration_seconds',
        'last_position_seconds',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'watched_ranges' => 'array',
            'watched_unique_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'last_position_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Merges freshly reported played seconds into the stored interval union
     * and refreshes the derived columns. This is the ONLY place the merge
     * happens — both the below-threshold poll write (`LessonProgressController`)
     * and the completion write (`MarkLessonCompleteAction`) funnel through it,
     * so the union semantics cannot drift between the two paths.
     *
     * @param  list<array<string, int>>|list<array{0: int, 1: int}>  $segments
     * @return int the resulting `watched_unique_seconds`
     */
    public function applyWatchedSegments(array $segments, ?int $durationSeconds = null): int
    {
        $duration = $durationSeconds ?? $this->duration_seconds;

        $merged = VideoWatchCalculator::merge(
            $this->watched_ranges ?? [],
            VideoWatchCalculator::normalize($segments, $duration ?? 0),
        );

        $this->watched_ranges = $merged;
        $this->watched_unique_seconds = VideoWatchCalculator::uniqueSeconds($merged);

        if ($durationSeconds !== null && $durationSeconds > 0) {
            $this->duration_seconds = $durationSeconds;
        }

        return $this->watched_unique_seconds;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
