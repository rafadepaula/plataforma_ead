<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use App\Models\Traits\OrgScope;
use Carbon\Carbon;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use AuditableTrait, HasFactory, OrgScope, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'title',
        'description',
        'workload_hours',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'workload_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    /**
     * Used to feed the "N módulos · N aulas" caption on `courses.index`
     * (`CourseController::index()`'s `withCount()`) without an N+1 query
     * per row — `Lesson` has no direct `course_id`, only `module_id`.
     *
     * @return HasManyThrough<Lesson, Module, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->using(CourseUser::class)
            ->withPivot(['enrolled_at', 'status', 'progress_percentage', 'completed_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<InvitationLink, $this>
     */
    public function invitationLinks(): HasMany
    {
        return $this->hasMany(InvitationLink::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<CourseCompletionRule, $this>
     */
    public function completionRules(): HasMany
    {
        return $this->hasMany(CourseCompletionRule::class);
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * a Course may not be soft-deleted while a student still
     * holds an `active` `course_user` enrollment (cancelled/completed
     * enrollments do not block deletion). Checked by both `CoursePolicy`
     * (so `Gate::authorize`/`@can` short-circuit) and the controller's
     * explicit 422 guard.
     */
    public function hasActiveEnrollments(): bool
    {
        return $this->students()->wherePivot('status', 'active')->exists();
    }

    /**
     * Inverse convenience helper for readability at call sites that guard
     * the delete action (`if (! $course->canBeDeleted()) { ... }`).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->hasActiveEnrollments();
    }

    /**
     * total published Lessons across this Course's
     * (non-soft-deleted) Modules, used as the denominator of the
     * student-progress percentage. `Module`/`Lesson` both carry
     * `SoftDeletes`, so a deleted Module/Lesson is excluded automatically
     * by their own global scope.
     */
    public function publishedLessonsCountFor(): int
    {
        return Lesson::query()
            ->where('is_published', true)
            ->whereHas('module', fn ($query) => $query->where('course_id', $this->id))
            ->count();
    }

    /**
     * count of this Course's published Lessons the given
     * User has completed (`lesson_progress.is_completed = true`), used as
     * the numerator of the student-progress percentage.
     */
    public function completedLessonsCountFor(User $user): int
    {
        return Lesson::query()
            ->where('is_published', true)
            ->whereHas('module', fn ($query) => $query->where('course_id', $this->id))
            ->whereHas('progress', fn ($query) => $query->where('user_id', $user->id)->where('is_completed', true))
            ->count();
    }

    /**
     * This Course's published Lessons (through non-soft-deleted
     * Modules), ordered the way a student would walk through the
     * course: by Module `order_index`, then Lesson `order_index` within
     * each Module. Backs `firstPublishedLessonFor()`/`resumeLessonFor()`.
     *
     * @return Collection<int, Lesson>
     */
    protected function publishedLessonsInOrder(): Collection
    {
        return Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('modules.course_id', $this->id)
            ->whereNull('modules.deleted_at')
            ->where('lessons.is_published', true)
            ->orderBy('modules.order_index')
            ->orderBy('lessons.order_index')
            ->select('lessons.*')
            ->get();
    }

    /**
     * The first Lesson a student sees when starting this Course from
     * scratch — the earliest published Lesson in module-then-lesson
     * `order_index` order, or `null` when the Course has none (e.g. every
     * Lesson is still a draft, or was soft-deleted).
     */
    public function firstPublishedLessonFor(): ?Lesson
    {
        return $this->publishedLessonsInOrder()->first();
    }

    /**
     * Resolves the "Continuar"/"Começar curso" CTA target for a given
     * student:
     *
     * - no published Lessons at all: `null` (caller must degrade the CTA);
     * - no `lesson_progress` touching any of this Course's published
     *   Lessons yet: the first published Lesson;
     * - otherwise: the most recently touched published Lesson, UNLESS
     *   the student already completed it, in which case the next
     *   not-yet-completed published Lesson after it is returned instead
     *   (falling back to the last-touched Lesson itself when every
     *   Lesson after it is also already completed).
     */
    public function resumeLessonFor(User $user): ?Lesson
    {
        $publishedLessons = $this->publishedLessonsInOrder();

        if ($publishedLessons->isEmpty()) {
            return null;
        }

        $progressRecords = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $publishedLessons->pluck('id'))
            ->get();

        return self::resolveResumeLesson($publishedLessons, $progressRecords);
    }

    /**
     * Pure ordering/tie-break algorithm behind `resumeLessonFor()`'s
     * "Continuar" CTA target, factored out so it can be shared between
     * this DB-querying implementation and
     * `StudentCourseController::resumeLessonFor()`'s in-memory port
     * (which builds `$progressRecords` from already eager-loaded
     * relations to avoid an N+1 query) — keeping the tie-break rule
     * maintained in exactly one place.
     *
     * @param  Collection<int, Lesson>  $publishedLessons  ordered module-then-lesson `order_index` (see `publishedLessonsInOrder()`)
     * @param  Collection<int, LessonProgress>  $progressRecords  every `lesson_progress` row touching `$publishedLessons` for the student, in any order
     */
    public static function resolveResumeLesson(Collection $publishedLessons, Collection $progressRecords): ?Lesson
    {
        if ($publishedLessons->isEmpty()) {
            return null;
        }

        $lastProgress = $progressRecords
            ->sortBy([['updated_at', 'desc'], ['id', 'desc']])
            ->first();

        if (! $lastProgress) {
            return $publishedLessons->first();
        }

        $lastLessonIndex = $publishedLessons->search(
            fn (Lesson $lesson): bool => $lesson->id === $lastProgress->lesson_id
        );

        $lastLesson = $publishedLessons->get($lastLessonIndex);

        if (! $lastProgress->is_completed) {
            return $lastLesson;
        }

        $completedLessonIds = $progressRecords
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->all();

        $nextIncomplete = $publishedLessons
            ->slice($lastLessonIndex + 1)
            ->first(fn (Lesson $lesson): bool => ! in_array($lesson->id, $completedLessonIds, true));

        return $nextIncomplete ?? $lastLesson;
    }

    /**
     * Derives the student-facing status chip for one `course_user`
     * enrollment row. Accepts a generic `object` (a real pivot model or a
     * plain stdClass with the same 4 fields) so callers can compose it
     * from an eager-loaded pivot without a DB round-trip:
     *
     * - `completed` pivot status always wins as `concluido`, even when
     *   `expires_at` is already in the past — completing a course before
     *   its deadline never regresses to "expired" after the fact;
     * - an `active` pivot whose `expires_at` is set and already past is
     *   `expirado`, regardless of `progress_percentage`;
     * - otherwise an `active` pivot is `nao_iniciado` when progress is
     *   zero, `em_andamento` when it is not.
     */
    public function enrollmentDisplayStatusFor(object $pivot): string
    {
        if ($pivot->status === 'completed') {
            return 'concluido';
        }

        if ($pivot->expires_at !== null) {
            $expiresAt = $pivot->expires_at instanceof \DateTimeInterface
                ? Carbon::instance($pivot->expires_at)
                : Carbon::parse($pivot->expires_at);

            if ($expiresAt->isPast()) {
                return 'expirado';
            }
        }

        return ($pivot->progress_percentage ?? 0) > 0 ? 'em_andamento' : 'nao_iniciado';
    }
}
