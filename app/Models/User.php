<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permissions\RolesEnum;
use App\Models\Traits\AuditableTrait;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * `org_id = null` for `admin` (queried globally) and, until enrollment,
 * `aluno`. `gestor` always has `org_id` set. `OrgScope` is intentionally
 * NOT applied to this model — see the `tenancy-architecture` skill.
 */
#[Fillable(['name', 'email', 'password', 'org_id', 'cpf', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditableTrait, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'org_id' => 'integer',
        ];
    }

    /**
     * Up to two uppercase letters derived from the first two words of the
     * user's name — the source of the initials in the forum surfaces: the
     * forum views pass it to `x-ui.avatar` and the polling endpoint ships
     * it in the payload, so a reply injected without a page reload reads
     * byte-identically to a server-rendered one.
     *
     * NOT app-wide: several screens still derive the fallback themselves
     * from a local `$initialsFor` closure (`users/index`,
     * `admin/users/index`, `quizzes/attempts/pending`,
     * `certificates/index`, `audit-logs/index`, `organizations/index`,
     * `courses/enrollments/index`), `x-ui.avatar`'s `name` prop re-derives
     * it in Blade, and `ForumPolling.js::initialsFrom()` mirrors it for
     * the degraded payload case. Changing the rule here means propagating
     * it to every one of those sites or the app renders two different
     * initials conventions for the same user.
     *
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $words = array_values(array_filter(preg_split('/\s+/', trim((string) $this->name)) ?: []));

            return mb_strtoupper(collect($words)
                ->take(2)
                ->map(fn (string $word): string => mb_substr($word, 0, 1))
                ->implode(''));
        });
    }

    /**
     * Human-readable label of the user's primary role, used as the badge
     * next to their name in forum posts and mirrored by the polling
     * payload. A user carrying no known role reads as "Membro".
     *
     * @return Attribute<string, never>
     */
    protected function roleLabel(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->getRoleNames()->first()) {
            RolesEnum::ADMIN->value => 'Admin',
            RolesEnum::GESTOR->value => 'Gestor',
            RolesEnum::ALUNO->value => 'Aluno',
            RolesEnum::PROFESSOR->value => 'Professor',
            default => 'Membro',
        });
    }

    /**
     * Send the password reset notification (  overridden to
     * use the localized `ResetPasswordNotification` instead of the
     * framework's default English copy).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->using(CourseUser::class)
            ->withPivot(['enrolled_at', 'status', 'progress_percentage', 'completed_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Courses this Professor is explicitly assigned to teach via the
     * `course_professor` pivot. Mirrors `courses()`'s pivot conventions
     * (`courses.org_id` is the tenant boundary; the pivot row itself is
     * the access boundary — same-org-without-assignment is still 403).
     *
     * @return BelongsToMany<Course, $this>
     */
    public function taughtCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_professor')
            ->withPivot(['assigned_by'])
            ->withTimestamps();
    }

    /**
     * The canonical "can this staff account act on `$course` as its
     * teacher?" helper — the ONLY place that consults the `course_professor`
     * pivot for access decisions. Policies (`ModulePolicy`,
     * `LessonPolicy`, `QuizAttemptPolicy`, `ForumTopicPolicy`,
     * `ForumReplyPolicy`) and `EnsureStudentIsEnrolled` branch on it
     * instead of duplicating the query.
     */
    public function teaches(Course $course): bool
    {
        return $this->taughtCourses()
            ->wherePivot('course_id', $course->id)
            ->exists();
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<InvitationLink, $this>
     */
    public function createdInvitationLinks(): HasMany
    {
        return $this->hasMany(InvitationLink::class, 'created_by');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * used by `EnsureStudentIsEnrolled` to gate an Aluno's
     * access to a Course's classroom/lesson/progress routes. Reads the
     * `course_user` pivot bypassing `Course`'s `OrgScope` (mirrors
     * `ProcessSmartInvitationAction`'s convention) — a `cancelled` status
     * or no row at all is not an active/completed enrollment.
     */
    public function hasActiveOrCompletedEnrollment(Course $course): bool
    {
        return $this->courses()
            ->withoutGlobalScopes()
            ->wherePivot('course_id', $course->id)
            ->wherePivotIn('status', ['active', 'completed'])
            ->exists();
    }

    /**
     * multiple attempts are always considered by
     * their best score: `MAX(score_percentage)` across this student's
     * `graded` attempts of the given Quiz (an `awaiting_manual_grading`
     * or `in_progress` attempt is excluded — only a fully graded attempt
     * counts). Returns `null` when the student has no graded attempt yet.
     */
    public function bestQuizScoreFor(Quiz $quiz): ?float
    {
        $best = $this->quizAttempts()
            ->where('quiz_id', $quiz->id)
            ->where('status', 'graded')
            ->max('score_percentage');

        return $best !== null ? (float) $best : null;
    }
}
