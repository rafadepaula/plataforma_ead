<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use App\Models\Traits\OrgScope;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

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
            ->withPivot(['enrolled_at', 'status', 'progress_percentage', 'completed_at'])
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
}
