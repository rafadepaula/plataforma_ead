<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cascade-inherited: org is implied by `module.course.org_id`. Do NOT
 * apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'module_id',
        'title',
        'type',
        'content_text',
        'youtube_url',
        'pdf_path',
        'image_path',
        'order_index',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasOne<Quiz, $this>
     */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
