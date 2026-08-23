<?php

namespace App\Models;

use Database\Factories\LessonMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *  one attached file (image or PDF) of a Lesson. A lesson may
 * carry any number of each kind; the legacy `lessons.image_path`/`pdf_path`
 * columns only ever held a single file and are kept in sync with the first
 * attachment of each kind for backward-compatible read paths — new consumers
 * must read through `Lesson::media()` instead of those columns.
 *
 * Cascade-inherited: org is implied by `lesson.module.course.org_id`. Do NOT
 * apply `OrgScope` here — see the `tenancy-architecture` skill.
 */
class LessonMedia extends Model
{
    /** @use HasFactory<LessonMediaFactory> */
    use HasFactory;

    public const string KIND_IMAGE = 'image';

    public const string KIND_PDF = 'pdf';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'kind',
        'path',
        'original_name',
        'size_bytes',
    ];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
