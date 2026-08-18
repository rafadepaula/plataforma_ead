<?php

namespace App\Events;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * dispatched by `EnrollmentController::store()`
 * (RF21, Gestor-driven) and `ProcessSmartInvitationAction` (self-service
 * invite flow, RF03) only on an actual transition into an active
 * enrollment: a brand-new `course_user` row, or a previously `cancelled`
 * one being reactivated — never on an already-active, unchanged
 * enrollment. `SendEnrollmentConfirmedNotification` is the sole listener,
 * auto-discovered from its `handle()` type-hint.
 */
class EnrollmentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Course $course,
        public User $user,
    ) {}
}
