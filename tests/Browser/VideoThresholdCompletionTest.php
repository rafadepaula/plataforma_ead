<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-07 RF20 — E2E coverage of the video auto-completion threshold.
 * Reaching 90% watched must auto-complete the lesson and recompute course
 * progress, without a page reload. Rather than depending on YouTube's real
 * network-bound IFrame API inside a headless browser, this drives
 * `resources/js/modules/LessonPlayer.js`'s public `reportProgress()` seam
 * directly via `Browser::script()` — the same JS entry point the real
 * poller calls every 5s from `player.getCurrentTime()`/`getDuration()`.
 */
class VideoThresholdCompletionTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_reaching_90_percent_watched_auto_completes_the_lesson_and_updates_course_progress(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->withYoutube()->create([
            'module_id' => $module->id,
            'is_published' => true,
        ]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-player-'.$lesson->id)
                ->assertMissing('@lesson-completed-badge');

            // `Browser::script()` returns the raw per-window result array
            // (not the fluent `Browser` instance), so it cannot be
            // chained like the other calls above/below it.
            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 90, 100);");

            $browser->waitFor('@lesson-completed-badge');
        });

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $lesson->module->course_id,
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
    }
}
