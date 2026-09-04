<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Events\LessonMarkedAsCompleted;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LessonProgressControllerTest extends TestCase
{
    private function createEnrolledAluno(Course $course): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'progress_percentage' => 0,
            'enrolled_at' => now(),
        ]);

        return $aluno;
    }

    private function createCourseWithModule(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        return [$course, $module];
    }

    public function test_student_can_manually_complete_text_lesson(): void
    {
        Event::fake([LessonMarkedAsCompleted::class]);

        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertOk()
            ->assertJson([
                'is_completed' => true,
            ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);

        Event::assertDispatched(LessonMarkedAsCompleted::class, function ($event) use ($lesson, $aluno) {
            return $event->lesson->id === $lesson->id && $event->user->id === $aluno->id;
        });
    }

    public function test_manual_completion_is_rejected_for_quiz_lesson_with_422(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'quiz',
            'is_published' => true,
        ]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Esta lição não pode ser concluída manualmente.',
            ]);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_manual_completion_is_rejected_for_video_lesson_with_422(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Esta lição não pode ser concluída manualmente.',
            ]);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * A `video_url` that cannot be resolved into a video id leaves the lesson
     * without a player, so the 90% threshold can never fire. Manual completion
     * must stay open, otherwise the lesson blocks course progress forever.
     */
    public function test_manual_completion_is_allowed_for_a_video_lesson_whose_url_is_unrecognizable(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->create(['is_published' => true]);
        DB::table('lessons')->where('id', $lesson->id)->update(['video_url' => 'https://vimeo.com/12345', 'video_provider' => null]);
        $lesson->refresh();

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $this->postJson(route('lessons.complete', $lesson))
            ->assertOk()
            ->assertJson(['is_completed' => true]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    /**
     * The mirror of the rule above: with no player there is no progress to
     * report, so the polling endpoint must refuse the same lesson.
     */
    public function test_progress_endpoint_is_rejected_for_a_video_lesson_whose_url_is_unrecognizable(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->create(['is_published' => true]);
        DB::table('lessons')->where('id', $lesson->id)->update(['video_url' => 'https://vimeo.com/12345', 'video_provider' => null]);
        $lesson->refresh();

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 95]],
        ])->assertStatus(422)->assertJson(['message' => 'Esta lição não é um vídeo.']);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_manual_completion_rejected_for_unpublished_lesson_with_404(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_manual_completion_rejected_for_non_enrolled_student_with_403(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.complete', $lesson));

        $response->assertForbidden();
    }

    public function test_video_progress_update_below_threshold_only_persists_watched_ranges(): void
    {
        Event::fake([LessonMarkedAsCompleted::class]);

        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 45]],
        ]);

        $response->assertOk()
            ->assertJson([
                'watched_unique_seconds' => 45,
                'is_completed' => false,
            ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 45,
            'is_completed' => false,
        ]);

        Event::assertNotDispatched(LessonMarkedAsCompleted::class);
    }

    public function test_video_progress_reaching_90_percent_of_unique_seconds_auto_completes_lesson(): void
    {
        Event::fake([LessonMarkedAsCompleted::class]);

        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 90]],
        ]);

        $response->assertOk()
            ->assertJson([
                'watched_unique_seconds' => 90,
                'is_completed' => true,
            ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'watched_unique_seconds' => 90,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);

        Event::assertDispatched(LessonMarkedAsCompleted::class, function ($event) use ($lesson, $aluno) {
            return $event->lesson->id === $lesson->id && $event->user->id === $aluno->id;
        });
    }

    public function test_video_progress_update_is_rejected_for_quiz_lesson_with_422(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'quiz',
            'is_published' => true,
        ]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 90]],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Esta lição não é um vídeo.',
            ]);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_video_progress_update_is_rejected_for_non_video_lesson_with_422(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 90]],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Esta lição não é um vídeo.',
            ]);
    }

    public function test_video_progress_update_rejected_for_unpublished_lesson_with_404(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => false]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 90]],
        ]);

        $response->assertNotFound();
    }

    public function test_video_progress_update_rejected_for_non_enrolled_student_with_403(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'duration_seconds' => 100,
            'segments' => [['start' => 0, 'end' => 90]],
        ]);

        $response->assertForbidden();
    }

    public function test_video_progress_update_validates_required_and_numeric_ranges(): void
    {
        [$course, $module] = $this->createCourseWithModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create(['is_published' => true]);

        $aluno = $this->createEnrolledAluno($course);
        $this->actingAs($aluno);

        $response = $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => -5, // campo legado: ignorado, nunca valida
            'duration_seconds' => 0,
            'segments' => [['start' => -5, 'end' => 1]],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_seconds', 'segments.0.start']);
    }
}
