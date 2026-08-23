<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `Course::firstPublishedLessonFor()`, `Course::resumeLessonFor()`,
 * and `Course::enrollmentDisplayStatusFor()` — the enrollment-navigation
 * logic student-facing "Meus cursos" (SPEC-26) resolves each card's CTA
 * target and status chip from.
 */
class CourseEnrollmentNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_published_lesson_for_returns_null_when_course_has_no_published_lessons(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create(['order_index' => 0]);
        Lesson::factory()->for($module)->create(['is_published' => false, 'order_index' => 0]);

        $this->assertNull($course->firstPublishedLessonFor());
    }

    public function test_first_published_lesson_for_orders_by_module_then_lesson_order_index(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $moduleTwo = Module::factory()->for($course)->create(['order_index' => 1]);
        $moduleOne = Module::factory()->for($course)->create(['order_index' => 0]);

        Lesson::factory()->for($moduleTwo)->create(['is_published' => true, 'order_index' => 0]);
        $expectedFirst = Lesson::factory()->for($moduleOne)->create(['is_published' => true, 'order_index' => 0]);
        Lesson::factory()->for($moduleOne)->create(['is_published' => true, 'order_index' => 1]);

        $result = $course->firstPublishedLessonFor();

        $this->assertNotNull($result);
        $this->assertTrue($result->is($expectedFirst));
    }

    public function test_first_published_lesson_for_skips_unpublished_lessons(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create(['order_index' => 0]);
        Lesson::factory()->for($module)->create(['is_published' => false, 'order_index' => 0]);
        $published = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 1]);

        $result = $course->firstPublishedLessonFor();

        $this->assertNotNull($result);
        $this->assertTrue($result->is($published));
    }

    public function test_resume_lesson_for_returns_null_when_course_has_no_published_lessons(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $user = User::factory()->create();

        $this->assertNull($course->resumeLessonFor($user));
    }

    public function test_resume_lesson_for_falls_back_to_first_published_lesson_when_no_progress_exists(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 0]);
        $user = User::factory()->create();

        $result = $course->resumeLessonFor($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->is($lesson));
    }

    public function test_resume_lesson_for_returns_the_most_recently_touched_lesson_when_all_are_completed(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create();
        $lessonOne = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 0]);
        $lessonTwo = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 1]);
        $user = User::factory()->create();

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonOne->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now()->subMinutes(10),
        ]);
        $lastTouched = LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonTwo->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);
        $lastTouched->forceFill(['updated_at' => now()])->save();

        $result = $course->resumeLessonFor($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->is($lessonTwo));
    }

    public function test_resume_lesson_for_returns_the_correct_partial_progress_target(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create();
        $lessonOne = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 0]);
        $lessonTwo = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 1]);
        $user = User::factory()->create();

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonOne->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now()->subMinutes(10),
        ])->forceFill(['updated_at' => now()->subMinutes(10)])->save();

        $result = $course->resumeLessonFor($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->is($lessonTwo));
    }

    public function test_resume_lesson_for_falls_back_when_last_touched_lesson_is_no_longer_published(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $module = Module::factory()->for($course)->create();
        $unpublished = Lesson::factory()->for($module)->create(['is_published' => false, 'order_index' => 0]);
        $stillPublished = Lesson::factory()->for($module)->create(['is_published' => true, 'order_index' => 1]);
        $user = User::factory()->create();

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $unpublished->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $result = $course->resumeLessonFor($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->is($stillPublished));
    }

    public function test_enrollment_display_status_for_returns_expirado_when_active_and_past_its_expiry_regardless_of_progress(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);

        $pastZeroProgress = (object) ['status' => 'active', 'progress_percentage' => 0, 'expires_at' => now()->subDay(), 'completed_at' => null];
        $pastPartialProgress = (object) ['status' => 'active', 'progress_percentage' => 99, 'expires_at' => now()->subDay(), 'completed_at' => null];

        $this->assertSame('expirado', $course->enrollmentDisplayStatusFor($pastZeroProgress));
        $this->assertSame('expirado', $course->enrollmentDisplayStatusFor($pastPartialProgress));
    }

    public function test_enrollment_display_status_for_never_returns_expirado_when_expires_at_is_null(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);

        $noProgress = (object) ['status' => 'active', 'progress_percentage' => 0, 'expires_at' => null, 'completed_at' => null];
        $someProgress = (object) ['status' => 'active', 'progress_percentage' => 40, 'expires_at' => null, 'completed_at' => null];

        $this->assertSame('nao_iniciado', $course->enrollmentDisplayStatusFor($noProgress));
        $this->assertSame('em_andamento', $course->enrollmentDisplayStatusFor($someProgress));
    }

    public function test_enrollment_display_status_for_returns_nao_iniciado_when_active_with_zero_progress(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $pivot = (object) ['status' => 'active', 'progress_percentage' => 0, 'expires_at' => null, 'completed_at' => null];

        $this->assertSame('nao_iniciado', $course->enrollmentDisplayStatusFor($pivot));
    }

    public function test_enrollment_display_status_for_returns_em_andamento_when_active_with_partial_progress(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $pivot = (object) ['status' => 'active', 'progress_percentage' => 55, 'expires_at' => now()->addDay(), 'completed_at' => null];

        $this->assertSame('em_andamento', $course->enrollmentDisplayStatusFor($pivot));
    }

    public function test_enrollment_display_status_for_returns_concluido_when_status_is_completed(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $pivot = (object) ['status' => 'completed', 'progress_percentage' => 100, 'expires_at' => null, 'completed_at' => now()];

        $this->assertSame('concluido', $course->enrollmentDisplayStatusFor($pivot));
    }
}
