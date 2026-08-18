<?php

namespace Tests\Feature;

use App\Actions\MarkLessonCompleteAction;
use App\Events\CourseCompletedByStudent;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * end-to-end coverage of the completion pipeline: marking a
 * Lesson complete via `MarkLessonCompleteAction` dispatches
 * `LessonMarkedAsCompleted`, which (through Laravel's auto-discovered
 * `RecalculateCourseProgress` listener, `QUEUE_CONNECTION=sync`)
 * recalculates `course_user.progress_percentage` and completes the
 * enrollment when the `all_lessons` rule's threshold is met.
 */
class CourseProgressCalculationTest extends TestCase
{
    public function test_completing_all_published_lessons_reaches_100_percent_and_completes_the_course(): void
    {
        Event::fake([CourseCompletedByStudent::class]);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['module_id' => $module->id, 'is_published' => true]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $action = new MarkLessonCompleteAction;

        $action->execute($lessons->first(), $user, 'manual_click');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 50,
            'status' => 'active',
        ]);

        $action->execute($lessons->last(), $user, 'manual_click');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 100,
            'status' => 'completed',
        ]);

        Event::assertDispatched(CourseCompletedByStudent::class);
    }

    public function test_progress_is_scoped_per_student_and_does_not_leak_across_users(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(4)->create(['module_id' => $module->id, 'is_published' => true]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $course->students()->attach($userA->id, ['enrolled_at' => now(), 'status' => 'active']);
        $course->students()->attach($userB->id, ['enrolled_at' => now(), 'status' => 'active']);

        $action = new MarkLessonCompleteAction;
        $action->execute($lessons->first(), $userA, 'manual_click');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $userA->id,
            'course_id' => $course->id,
            'progress_percentage' => 25,
        ]);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $userB->id,
            'course_id' => $course->id,
            'progress_percentage' => 0,
        ]);
    }

    public function test_unpublishing_a_lesson_after_completion_does_not_retroactively_uncomplete_the_course(): void
    {
        Event::fake([CourseCompletedByStudent::class]);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['module_id' => $module->id, 'is_published' => true]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $action = new MarkLessonCompleteAction;
        $action->execute($lessons->first(), $user, 'manual_click');
        $action->execute($lessons->last(), $user, 'manual_click');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'completed',
        ]);

        $lessons->last()->update(['is_published' => false]);

        // No recalculation path runs on unpublish alone — the already
        // -persisted completion must remain untouched.
        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
    }
}
