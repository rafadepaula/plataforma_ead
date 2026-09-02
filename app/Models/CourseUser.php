<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot for the `course_user` join table, attached to the
 * `belongsToMany` relations via `->using()` (`Course::students()` /
 * `User::courses()`). Without a pivot class the pivot columns never get
 * casts, so date columns arrived at views/services as raw DB strings —
 * which is what left the enrollments screen's "Matriculado em" cell
 * silently empty (`optional($pivot->enrolled_at)->format()` on a string
 * resolves to `null` under PHP 8). The relation still declares the
 * `withPivot([...])` whitelist; this class only adds type casts.
 */
class CourseUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'course_user';

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
