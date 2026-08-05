<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-07 RF20 — POST /lessons/{lesson}/progress: the AJAX polling target
 * hit every 5s by `LessonPlayer.js` while a video lesson plays. Only
 * persists `watched_seconds` (GREATEST) below the 90% threshold; calls
 * `MarkLessonCompleteAction` with `completion_source=video_threshold` once
 * `watched_seconds/duration_seconds >= 0.90`. HTTP-level companion to
 * bucket 1's `MarkLessonCompleteActionTest` unit test.
 */
class VideoThresholdCompletionTest extends TestCase
{
    private function enrolledAlunoAndVideoLesson(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return [$aluno, $lesson];
    }

    public function test_progress_below_threshold_only_persists_watched_seconds(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 30,
            'duration_seconds' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 30,
            'is_completed' => false,
        ]);
    }

    public function test_progress_reaching_90_percent_auto_completes_the_lesson(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 90,
            'duration_seconds' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 90,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);
    }

    public function test_watched_seconds_never_decreases_on_a_later_smaller_report(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 80,
            'duration_seconds' => 100,
        ])->assertOk();

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 40,
            'duration_seconds' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 80,
        ]);
    }

    public function test_progress_endpoint_is_rejected_for_a_non_video_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 90,
            'duration_seconds' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_progress_requires_watched_seconds_and_duration_seconds(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['watched_seconds', 'duration_seconds']);
    }

    public function test_progress_endpoint_is_rejected_for_a_quiz_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 90,
            'duration_seconds' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_progress_endpoint_is_rejected_for_an_unpublished_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => false]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 90,
            'duration_seconds' => 100,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_progress_endpoint_is_rejected_for_a_malformed_lesson_with_both_quiz_type_and_youtube_url(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create([
            'type' => 'quiz',
            'is_published' => true,
        ]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 90,
            'duration_seconds' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }
}
