<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use App\Services\VideoUrlSanitizerManager;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
        'video_provider',
        'video_url',
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
     *  the provider (`youtube`|`vimeo`) that owns this lesson's
     * `video_url`: the stored `video_provider` column when it holds a
     * known provider, else detected from the URL itself — the drift
     * fallback for rows saved without (or before) the column. `null` when
     * there is neither a known provider nor a recognizable URL.
     *
     * @return Attribute<?string, never>
     */
    protected function videoProvider(): Attribute
    {
        return Attribute::get(function (): ?string {
            $stored = $this->attributes['video_provider'] ?? null;

            if (is_string($stored) && in_array($stored, VideoUrlSanitizerManager::PROVIDERS, true)) {
                return $stored;
            }

            return app(VideoUrlSanitizerManager::class)->providerFor($this->attributes['video_url'] ?? null);
        });
    }

    /**
     *  the provider video id resolved from `video_url`, regardless of
     * the stored form (`watch?v=`, `embed/`, `youtu.be/`, `vimeo.com/{id}`,
     * `player.vimeo.com/video/{id}`, ...), or `null` when the column is
     * empty or holds something no sanitizer recognizes. Consumers must
     * branch on `null` instead of assuming the stored value is already
     * sanitized — the `null` state is what keeps an unparseable lesson
     * manually completable instead of freezing the course.
     *
     * @return Attribute<?string, never>
     */
    protected function videoId(): Attribute
    {
        return Attribute::get(function (): ?string {
            $provider = $this->video_provider;

            if ($provider === null) {
                return null;
            }

            return app(VideoUrlSanitizerManager::class)->for($provider)->extractVideoId($this->video_url);
        });
    }

    /**
     *  the canonical, embeddable URL for the resolved provider
     * (`https://www.youtube-nocookie.com/embed/{id}` or
     * `https://player.vimeo.com/video/{id}`), or `null` when no video id
     * can be resolved. This is the only URL form a consumer may hand a
     * player — both providers refuse to be framed from any other form.
     *
     * @return Attribute<?string, never>
     */
    protected function videoEmbedUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $provider = $this->video_provider;

            if ($provider === null) {
                return null;
            }

            return app(VideoUrlSanitizerManager::class)->for($provider)->tryCanonicalize($this->video_url);
        });
    }

    /**
     * Whether the lesson carries a video whose id resolves into a player
     * of a known provider — the server-side predicate shared by the
     * dispatch view, the 422 shape guards of `LessonProgressController`
     * and the completion bar's `manual` flag. A lesson whose URL cannot be
     * parsed has no player to drive the 90% threshold, so it falls back to
     * manual completion instead.
     */
    public function hasPlayableVideo(): bool
    {
        return $this->video_id !== null;
    }

    /**
     * Deprecated BC alias of `video_id`; new code reads `video_id`.
     *
     * @return Attribute<?string, never>
     */
    protected function youtubeVideoId(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->video_id);
    }

    /**
     * Deprecated BC alias of `video_embed_url`; new code reads
     * `video_embed_url`.
     *
     * @return Attribute<?string, never>
     */
    protected function youtubeEmbedUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->video_embed_url);
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
            filled($this->video_url) => 'play',
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

    /**
     *  the single source of this lesson's PDF list, in render order.
     * Prefers the `media` relation (kind=pdf, ordered by id); when it is
     * empty, falls back to a synthetic transient `LessonMedia` built from
     * the deprecated flat `pdf_path` column so legacy single-file lessons
     * keep rendering. Returns an empty collection when neither exists.
     *
     * Consumers (classroom partial, gated stream endpoint) must read
     * through here — never through `media` + `pdf_path` inline — so the
     * fallback rule lives in exactly one place.
     *
     * @return Collection<int, LessonMedia>
     */
    public function pdfAttachments(): Collection
    {
        $pdfs = $this->relationLoaded('media')
            ? $this->media->where('kind', LessonMedia::KIND_PDF)->sortBy('id')->values()
            : $this->pdfs()->orderBy('id')->get();

        if ($pdfs->isNotEmpty()) {
            return $pdfs->values();
        }

        if (filled($this->pdf_path)) {
            return collect([
                new LessonMedia([
                    'kind' => LessonMedia::KIND_PDF,
                    'path' => $this->pdf_path,
                    'original_name' => basename($this->pdf_path),
                ]),
            ]);
        }

        return collect();
    }
}
