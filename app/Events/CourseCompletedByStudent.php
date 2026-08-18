<?php

namespace App\Events;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * dispatched by `RecalculateCourseProgress` when
 * `course_user.progress_percentage` reaches the `course_completion_rules`
 * (`rule_type = all_lessons`) `required_percentage` for the given Course/
 * User pair.
 */
class CourseCompletedByStudent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Course $course,
        public User $user,
    ) {}
}
