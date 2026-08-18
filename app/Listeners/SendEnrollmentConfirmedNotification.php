<?php

namespace App\Listeners;

use App\Events\EnrollmentConfirmed;
use App\Notifications\EnrollmentConfirmedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * auto-discovered (type-hinted `handle()`
 * parameter). * bubble up and roll back the enrollment that just committed, so the
 * `->notify()` call is wrapped in try/catch and logged rather than left
 * to propagate — `CertificateIssuedNotification`'s `database` channel is
 * ordered before `mail`, so the in-app row still persists regardless.
 */
class SendEnrollmentConfirmedNotification
{
    public function handle(EnrollmentConfirmed $event): void
    {
        try {
            $event->user->notify(new EnrollmentConfirmedNotification($event->course));
        } catch (Throwable $exception) {
            Log::error('Falha ao enviar notificação de matrícula confirmada.', [
                'course_id' => $event->course->id,
                'user_id' => $event->user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
