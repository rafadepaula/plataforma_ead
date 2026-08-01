<?php

namespace App\Models;

use Database\Factories\CourseCompletionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cascade-inherited: org is implied by `course.org_id`. Do NOT apply
 * `OrgScope` here — see the `tenancy-architecture` skill. `target_id` is a
 * pseudo-polymorphic pointer with no real DB foreign key — its integrity
 * (pointing to `modules.id` or `quizzes.id` depending on `rule_type`) is
 * validated at the application layer only.
 */
class CourseCompletionRule extends Model
{
    /** @use HasFactory<CourseCompletionRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'rule_type',
        'target_id',
        'required_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_percentage' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
