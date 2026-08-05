<?php

namespace App\Events;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SPEC-07 RF20 — dispatched by `MarkLessonCompleteAction` only on the
 * `is_completed` false → true transition (never re-dispatched on an
 * idempotent re-completion). `RecalculateCourseProgress` is the sole
 * listener, auto-discovered from its `handle()` type-hint.
 */
class LessonMarkedAsCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Lesson $lesson,
        public User $user,
    ) {}
}
