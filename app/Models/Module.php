<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cascade-inherited: org is implied by `course.org_id`. Do NOT apply
 * `OrgScope` here — see the `tenancy-architecture` skill.
 */
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order_index',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
