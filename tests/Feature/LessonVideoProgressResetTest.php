<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PUT /lessons/{lesson}: the opt-in "Resetar progresso dos alunos neste
 * vídeo" flow — when the Gestor ticks the checkbox AND the sanitized
 * `video_url` actually changed, `ResetLessonVideoProgressAction` zeroes
 * every student's `lesson_progress` row for that video (in place) and
 * recomputes the `course_user` pivot (reverting `completed` when the
 * course no longer satisfies its rules). Any other combination keeps the
 * progress untouched.
 */
class LessonVideoProgressResetTest extends TestCase
{
    private function makeLessonWithCompletedStudent(): array
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'completed',
            'enrolled_at' => now(),
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        $progress = LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
            'watched_ranges' => [[0, 60], [120, 180]],
            'watched_unique_seconds' => 120,
            'duration_seconds' => 200,
            'last_position_seconds' => 180,
            'completed_at' => now(),
        ]);

        return [$lesson, $aluno, $progress];
    }

    private function updatePayload(Lesson $lesson, array $overrides = []): array
    {
        return array_merge([
            'title' => $lesson->title,
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            'reset_video_progress' => '1',
        ], $overrides);
    }

    public function test_swapping_the_video_url_with_reset_zeroes_progress_and_reverts_the_pivot(): void
    {
        [$lesson, $aluno, $progress] = $this->makeLessonWithCompletedStudent();

        $response = $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson));

        $response->assertRedirect(route('modules.lessons.index', $lesson->module));

        // Row preserved, columns zeroed in place.
        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => false,
            'completion_source' => null,
            'watched_unique_seconds' => 0,
            'duration_seconds' => 0,
            'last_position_seconds' => 0,
            'watched_ranges' => null,
            'completed_at' => null,
        ]);

        // Single-lesson course no longer satisfies completion → reverted.
        $this->assertDatabaseHas('course_user', [
            'user_id' => $aluno->id,
            'course_id' => $lesson->module->course_id,
            'status' => 'active',
            'progress_percentage' => 0,
            'completed_at' => null,
        ]);
    }

    public function test_swapping_the_video_url_without_the_checkbox_keeps_progress_intact(): void
    {
        [$lesson, $aluno, $progress] = $this->makeLessonWithCompletedStudent();

        $response = $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson, [
            'reset_video_progress' => '0',
        ]));

        $response->assertRedirect(route('modules.lessons.index', $lesson->module));

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
            'watched_unique_seconds' => 120,
        ]);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $aluno->id,
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
    }

    public function test_the_checkbox_is_ignored_when_the_video_url_did_not_change(): void
    {
        [$lesson, $aluno, $progress] = $this->makeLessonWithCompletedStudent();

        $response = $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson, [
            // Mesma URL canônica já persistida na lição.
            'video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ]));

        $response->assertRedirect(route('modules.lessons.index', $lesson->module));

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'is_completed' => true,
            'watched_unique_seconds' => 120,
        ]);
    }

    public function test_clearing_the_video_url_never_resets_progress(): void
    {
        [$lesson, $aluno, $progress] = $this->makeLessonWithCompletedStudent();

        $response = $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson, [
            'video_url' => '',
        ]));

        $response->assertRedirect(route('modules.lessons.index', $lesson->module));

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'is_completed' => true,
            'watched_unique_seconds' => 120,
        ]);
    }

    public function test_a_student_who_never_watched_is_a_safe_no_op(): void
    {
        [$lesson, , $progress] = $this->makeLessonWithCompletedStudent();
        $progress->delete();

        $response = $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson));

        $response->assertRedirect(route('modules.lessons.index', $lesson->module));
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_the_lesson_update_is_registered_in_the_audit_log(): void
    {
        [$lesson] = $this->makeLessonWithCompletedStudent();

        DB::table('audit_logs')->delete();

        $this->put(route('lessons.update', $lesson), $this->updatePayload($lesson))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => $lesson->getMorphClass().'.updated',
            'auditable_type' => $lesson->getMorphClass(),
            'auditable_id' => $lesson->id,
        ]);
    }
}
