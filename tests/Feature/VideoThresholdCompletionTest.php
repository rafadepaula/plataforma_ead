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
 * POST /lessons/{lesson}/progress: the AJAX polling target
 * hit every 5s by the lesson player while a video lesson plays. The client
 * reports raw played segments; the server unions them into
 * `lesson_progress.watched_ranges` and reads the 90% auto-completion
 * threshold from `watched_unique_seconds` — never from the playhead. HTTP
 * companion to `MarkLessonCompleteActionTest`.
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

    /**
     * @return array<string, mixed>
     */
    private function payload(int $start, int $end, int $duration): array
    {
        return [
            'duration_seconds' => $duration,
            'segments' => [['start' => $start, 'end' => $end]],
        ];
    }

    /**
     * The acceptance example of the tracking task: 10min video, student
     * watches 0–2min, seeks to 8min, watches until 9min. Unique watched
     * seconds are 180 = 30%, so the lesson must NOT auto-complete even
     * though the playhead reached 90% of the duration.
     */
    public function test_seeking_forward_only_credits_the_seconds_actually_watched(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 120, 600))->assertOk();

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(480, 540, 600));

        $response->assertOk()->assertJson([
            'watched_unique_seconds' => 180,
            'is_completed' => false,
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 180,
            'is_completed' => false,
        ]);
    }

    public function test_progress_below_threshold_only_persists_the_watched_ranges(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 30, 100));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 30,
            'is_completed' => false,
        ]);
    }

    public function test_progress_reaching_90_percent_of_unique_seconds_auto_completes_the_lesson(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 90, 100));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 90,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);
    }

    public function test_completion_requires_unique_seconds_not_the_playhead(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        // Playhead at 95% (a seek to second 95) but only 5s actually watched.
        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(95, 100, 100));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 5,
            'is_completed' => false,
        ]);
    }

    public function test_the_union_never_regresses_and_replay_never_double_counts(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 80, 100))->assertOk();

        // A later, smaller batch replaying already-watched seconds changes nothing.
        $this->postJson(route('lessons.progress', $lesson), $this->payload(40, 60, 100))->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 80,
        ]);
    }

    /**
     * The resume bookmark travels even when NOTHING new was watched: seek
     * to an unwatched position and reload before the 5s poll — the batch
     * carries an EMPTY segments array plus the new playhead, which a
     * `required` rule on `segments` would reject (the exact regression that
     * made reloads fall back to the pre-seek position).
     */
    public function test_a_position_only_batch_persists_the_resume_bookmark(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 30]],
            'position_seconds' => 30,
        ])->assertOk();

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [],
            'position_seconds' => 80,
        ]);

        $response->assertOk()->assertJson([
            'watched_unique_seconds' => 30,
            'last_position_seconds' => 80,
            'is_completed' => false,
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 30,
            'last_position_seconds' => 80,
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

        $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 90, 100))
            ->assertStatus(422);
    }

    public function test_progress_requires_segments_and_duration_seconds(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['segments', 'duration_seconds']);
    }

    public function test_progress_validates_the_segment_shape_and_bounds(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [
                ['start' => -5, 'end' => 10], // negative start
                ['end' => 20],                // missing start
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['segments.0.start', 'segments.1.start']);
    }

    public function test_segments_beyond_the_duration_are_clamped_not_rejected(): void
    {
        [$aluno, $lesson] = $this->enrolledAlunoAndVideoLesson();
        $this->actingAs($aluno);

        // Provider jitter can overshoot by a second; the server clamps to
        // [0, duration] instead of 422-ing a legitimate batch.
        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 120, 100));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 100,
            'is_completed' => true,
        ]);
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

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 90, 100));

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

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 90, 100));

        $response->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_progress_endpoint_is_rejected_for_a_malformed_lesson_with_both_quiz_type_and_video_url(): void
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

        $response = $this->postJson(route('lessons.progress', $lesson), $this->payload(0, 90, 100));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }
}
