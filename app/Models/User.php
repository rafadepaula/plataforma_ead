<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
    use HasFactory, HasRoles, Notifiable;

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
     * Send the password reset notification (SPEC-04 RF02 — overridden to
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
            ->withPivot(['enrolled_at', 'status', 'progress_percentage', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
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
     * SPEC-07 RF20 — used by `EnsureStudentIsEnrolled` to gate an Aluno's
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
}
