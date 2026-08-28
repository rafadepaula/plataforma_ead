<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use App\Services\YoutubeSanitizerService;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     *  the 11-char YouTube video id resolved from `youtube_url`,
     * regardless of the stored form (`embed/`, `watch?v=`, `youtu.be/`), or
     * `null` when the column is empty or holds something that is not a
     * recognizable YouTube link. Consumers must branch on `null` instead of
     * assuming the stored value is already sanitized.
     *
     * @return Attribute<?string, never>
     */
    protected function youtubeVideoId(): Attribute
    {
        return Attribute::get(
            fn (): ?string => app(YoutubeSanitizerService::class)->extractVideoId($this->youtube_url)
        );
    }

    /**
     *  the canonical, embeddable `https://www.youtube.com/embed/{id}`
     * URL, or `null` when no video id can be resolved. YouTube refuses to be
     * framed from any other URL form, so this is the only value a consumer may
     * put in an `<iframe src>`.
     *
     * @return Attribute<?string, never>
     */
    protected function youtubeEmbedUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => app(YoutubeSanitizerService::class)->tryCanonicalize($this->youtube_url)
        );
    }

    /**
     *  the Lucide glyph that represents this lesson's content kind
     * while it is still PENDING (`play` for video, `file-text` for PDF,
     * `clipboard` for a quiz, `book-open` for plain text). Completion is a
     * per-student fact the model knows nothing about, so the `check` glyph of
     * a completed lesson is decided by the consumer, not here.
     *
     * Reads PDFs through the `media` relation (the flat `pdf_path` column is
     * a deprecated single-file leftover, kept only as a legacy fallback), so
     * consumers rendering many lessons MUST eager-load `media`.
     *
     * @return Attribute<string, never>
     */
    protected function pendingGlyph(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->type === 'quiz' => 'clipboard',
            filled($this->youtube_url) => 'play',
            $this->hasPdfAttachment() => 'file-text',
            default => 'book-open',
        });
    }

    /**
     *  whether the lesson carries a PDF, in `lesson_media` or in the
     * deprecated flat `pdf_path` column. Uses the already-loaded `media`
     * relation when present to stay N+1-free inside a lesson loop.
     */
    public function hasPdfAttachment(): bool
    {
        if (filled($this->pdf_path)) {
            return true;
        }

        if ($this->relationLoaded('media')) {
            return $this->media->contains(fn (LessonMedia $media): bool => $media->kind === LessonMedia::KIND_PDF);
        }

        return $this->pdfs()->exists();
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     *  every attached file (images + PDFs) of this lesson. New
     * consumers must read lesson media through this relation — the flat
     * `image_path`/`pdf_path` columns are deprecated single-file leftovers,
     * only kept (synced with the first attachment of each kind) for legacy
     * read paths.
     *
     * @return HasMany<LessonMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(LessonMedia::class);
    }

    /**
     *  only the image attachments of this lesson.
     *
     * @return HasMany<LessonMedia, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(LessonMedia::class)->where('kind', LessonMedia::KIND_IMAGE);
    }

    /**
     *  only the PDF attachments of this lesson.
     *
     * @return HasMany<LessonMedia, $this>
     */
    public function pdfs(): HasMany
    {
        return $this->hasMany(LessonMedia::class)->where('kind', LessonMedia::KIND_PDF);
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
