<?php

namespace App\Listeners;

use App\Enums\Permissions\RolesEnum;
use App\Events\ForumTopicPosted;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Notifications\ForumAnnouncementNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-discovered listener (type-hinted `handle()` parameter).
 * When a Professor creates a ForumTopic (an announcement), all students
 * with active/completed enrollments in that Course are notified via database and mail.
 * Each student notification is isolated with its own try/catch so mail failure
 * never breaks the loop or rolls back database state.
 */
class SendForumAnnouncementNotifications
{
    public function handle(ForumTopicPosted $event): void
    {
        $topic = ForumTopic::query()->withoutGlobalScopes()->findOrFail($event->topic->id);
        $author = $topic->user;

        if (! $author || ! $author->hasRole(RolesEnum::PROFESSOR->value)) {
            return;
        }

        $course = Course::query()->withoutGlobalScopes()->findOrFail($topic->course_id);

        $students = $course->students()
            ->withoutGlobalScopes()
            ->wherePivotIn('status', ['active', 'completed'])
            ->where('users.id', '!=', $author->id)
            ->get();

        foreach ($students as $student) {
            try {
                $student->notify(new ForumAnnouncementNotification($topic, $course));
            } catch (Throwable $exception) {
                Log::error('Falha ao enviar notificação de anúncio do fórum.', [
                    'topic_id' => $topic->id,
                    'course_id' => $course->id,
                    'recipient_id' => $student->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
