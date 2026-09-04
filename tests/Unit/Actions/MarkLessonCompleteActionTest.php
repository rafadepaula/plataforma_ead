<?php

namespace Tests\Unit\Actions;

use App\Actions\MarkLessonCompleteAction;
use App\Events\LessonMarkedAsCompleted;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `MarkLessonCompleteAction` is the single write path for
 * `lesson_progress`: idempotent completion, watched time persisted as the
 * UNION of played ranges (never `GREATEST` of a playhead), `completed_at`
 * set once, `LessonMarkedAsCompleted` dispatched only on the false -> true
 * transition.
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

    public function test_watched_time_is_persisted_as_the_union_of_played_ranges(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        $progress = (new MarkLessonCompleteAction)->execute(
            $lesson,
            $user,
            'video_threshold',
            [['start' => 0, 'end' => 120]],
            600,
        );

        $this->assertSame(120, $progress->watched_unique_seconds);
        $this->assertSame([[0, 120]], $progress->watched_ranges);
        $this->assertSame(600, $progress->duration_seconds);

        // A later batch reporting a forward-seeked segment extends the union
        // instead of inflating it — 0–2min + 8–9min of a 10min video is 3min.
        $progress = (new MarkLessonCompleteAction)->execute(
            $lesson,
            $user,
            'video_threshold',
            [['start' => 480, 'end' => 540]],
            600,
        );

        $this->assertSame(180, $progress->watched_unique_seconds);
        $this->assertSame([[0, 120], [480, 540]], $progress->watched_ranges);

        // Replaying an already-covered stretch never double-counts.
        $progress = (new MarkLessonCompleteAction)->execute(
            $lesson,
            $user,
            'video_threshold',
            [['start' => 30, 'end' => 90]],
            600,
        );

        $this->assertSame(180, $progress->watched_unique_seconds);
        $this->assertSame([[0, 120], [480, 540]], $progress->watched_ranges);
    }

    public function test_manual_completion_without_watched_segments_leaves_the_ranges_empty(): void
    {
        $lesson = $this->makeLesson();
        $user = User::factory()->create();

        (new MarkLessonCompleteAction)->execute($lesson, $user, 'manual_click');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 0,
        ]);

        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $this->assertNull($progress->watched_ranges);
        $this->assertNull($progress->duration_seconds);
    }
}
