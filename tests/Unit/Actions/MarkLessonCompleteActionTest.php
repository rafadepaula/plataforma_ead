<?php

namespace Tests\Unit\Actions;

use App\Actions\MarkLessonCompleteAction;
use App\Events\LessonMarkedAsCompleted;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `MarkLessonCompleteAction` is the single write path for
 * `lesson_progress`: idempotent completion, `GREATEST` watched_seconds,
 * `completed_at` set once, `LessonMarkedAsCompleted` dispatched only on
 * the false -> true transition.
 */
class MarkLessonCompleteActionTest extends TestCase
{
    private function makeLesson(): Lesson
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
    }

    public function test_first_completion_creates_progress_row_and_dispatches_event(): void
    {
        Event::fake();

        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        $progress = (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        $this->assertTrue($progress->is_completed);
        $this->assertNotNull($progress->completed_at);
        $this->assertSame('manual_click', $progress->completion_source);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);

        Event::assertDispatched(LessonMarkedAsCompleted::class, fn ($event) => $event->lesson->is($lesson) && $event->user->is($user));
    }

    public function test_re_completing_an_already_completed_lesson_does_not_redispatch_event(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        Event::fake();

        $progress = (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        $this->assertTrue($progress->is_completed);
        Event::assertNotDispatched(LessonMarkedAsCompleted::class);
    }

    public function test_completed_at_is_only_set_on_first_completion(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        $first = (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');
        $firstCompletedAt = $first->completed_at;

        $this->travel(1)->hours();

        $second = (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        $this->assertTrue($firstCompletedAt->equalTo($second->completed_at));
    }

    public function test_watched_seconds_is_persisted_as_the_greatest_of_current_and_reported(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'video_threshold', 120);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 120,
        ]);

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'video_threshold', 50);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 120,
        ]);

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'video_threshold', 200);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 200,
        ]);
    }

    public function test_manual_completion_without_watched_seconds_leaves_it_null(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => null,
        ]);
    }
}
