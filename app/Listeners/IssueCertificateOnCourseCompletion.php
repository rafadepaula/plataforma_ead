<?php

namespace App\Listeners;

use App\Actions\IssueCertificateAction;
use App\Events\CourseCompletedByStudent;

/**
 * SPEC-09 §1.1 — auto-discovered (type-hinted `handle()` parameter, mirrors
 * `RecalculateCourseProgress`'s convention, no explicit provider
 * registration). Runs synchronously (`QUEUE_CONNECTION=sync`) right after
 * `CourseCompletedByStudent` fires, delegating the full eligibility
 * evaluation + idempotent issuance to `IssueCertificateAction`.
 */
class IssueCertificateOnCourseCompletion
{
    public function __construct(protected IssueCertificateAction $issueCertificateAction) {}

    public function handle(CourseCompletedByStudent $event): void
    {
        $this->issueCertificateAction->execute($event->course, $event->user);
    }
}
