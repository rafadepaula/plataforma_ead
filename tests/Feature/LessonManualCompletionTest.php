<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-07 RF20 — POST /lessons/{lesson}/complete: manual completion is
 * only valid for text/PDF/image lessons (never `type=quiz` nor a lesson
 * carrying a `youtube_url`, which must go through the video-threshold
 * endpoint instead).
 */
class LessonManualCompletionTest extends TestCase
{
    private function enrolledAluno(Course $course): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return $aluno;
    }

    public function test_student_can_manually_complete_a_text_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    public function test_manual_completion_is_rejected_for_a_quiz_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_manual_completion_is_rejected_for_a_video_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_manual_completion_is_rejected_for_a_malformed_lesson_with_both_quiz_type_and_youtube_url(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create([
            'type' => 'quiz',
            'is_published' => true,
        ]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_re_completing_an_already_completed_lesson_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $this->postJson(route('lessons.complete', $lesson))->assertOk();
        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertOk();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
        $this->assertSame(1, LessonProgress::query()
            ->where('user_id', $aluno->id)
            ->where('lesson_id', $lesson->id)
            ->count());
    }

    public function test_a_student_cannot_manually_complete_an_unpublished_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        $aluno = $this->enrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_a_non_enrolled_student_cannot_complete_a_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertForbidden();
    }
}
