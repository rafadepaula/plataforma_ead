<?php

namespace Tests\Unit\Listeners;

use App\Actions\EvaluateCourseCompletionAction;
use App\Events\CourseCompletedByStudent;
use App\Events\LessonMarkedAsCompleted;
use App\Listeners\RecalculateCourseProgress;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `RecalculateCourseProgress` recomputes
 * `course_user.progress_percentage` for the completing student and
 * completes the enrollment when **every** registered
 * `course_completion_rules` row is satisfied — `all_lessons` is one
 * parametrized rule among them, never a mandatory gate.
 */
class RecalculateCourseProgressTest extends TestCase
{
    public function test_recalculates_progress_percentage_after_a_lesson_is_completed(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(4)->create(['module_id' => $module->id, 'is_published' => true]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $lessons->first()->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lessons->first(), $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 25,
            'status' => 'active',
        ]);
    }

    public function test_ignores_unpublished_lessons_in_the_denominator(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $publishedLessons = Lesson::factory()->count(2)->create(['module_id' => $module->id, 'is_published' => true]);
        Lesson::factory()->count(3)->create(['module_id' => $module->id, 'is_published' => false]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $publishedLessons->first()->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($publishedLessons->first(), $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 50,
        ]);
    }

    public function test_a_course_with_zero_published_lessons_does_not_divide_by_zero(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => false]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $lesson->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lesson, $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 0,
        ]);
    }

    public function test_reaching_required_percentage_completes_enrollment_and_dispatches_event(): void
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

        foreach ($lessons as $lesson) {
            $lesson->progress()->create([
                'user_id' => $user->id,
                'is_completed' => true,
                'completed_at' => now(),
            ]);
        }

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lessons->last(), $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 100,
            'status' => 'completed',
        ]);

        Event::assertDispatched(CourseCompletedByStudent::class, fn ($event) => $event->course->is($course) && $event->user->is($user));
    }

    public function test_an_unsatisfied_rule_does_not_complete_the_course_or_dispatch_event(): void
    {
        Event::fake([CourseCompletedByStudent::class]);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'min_quiz_score',
            'required_percentage' => 100,
        ]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $lesson->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lesson, $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 100,
            'status' => 'active',
        ]);

        Event::assertNotDispatched(CourseCompletedByStudent::class);
    }

    public function test_no_completion_rules_at_all_does_not_crash(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $lesson->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lesson, $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 100,
            'status' => 'active',
        ]);
    }

    public function test_a_satisfied_min_quiz_score_only_rule_completes_the_course_without_any_all_lessons_rule(): void
    {
        Event::fake([CourseCompletedByStudent::class]);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'min_quiz_score',
            'target_id' => $quiz->id,
            'required_percentage' => 80,
        ]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        QuizAttempt::factory()->for($quiz)->for($user)->graded()->create(['score_percentage' => 90]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lesson, $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'completed',
        ]);

        Event::assertDispatched(CourseCompletedByStudent::class, fn ($event) => $event->course->is($course) && $event->user->is($user));
    }

    public function test_all_lessons_rule_honors_its_configured_percentage_instead_of_a_hardcoded_100(): void
    {
        Event::fake([CourseCompletedByStudent::class]);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['module_id' => $module->id, 'is_published' => true]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 50,
        ]);

        $user = User::factory()->create();
        $course->students()->attach($user->id, ['enrolled_at' => now(), 'status' => 'active']);

        $lessons->first()->progress()->create([
            'user_id' => $user->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        (new RecalculateCourseProgress(new EvaluateCourseCompletionAction))->handle(new LessonMarkedAsCompleted($lessons->first(), $user));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress_percentage' => 50,
            'status' => 'completed',
        ]);

        Event::assertDispatched(CourseCompletedByStudent::class);
    }
}
