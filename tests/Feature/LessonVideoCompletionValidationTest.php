<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use Tests\TestCase;

/**
 * The per-lesson video completion threshold
 * (`lessons.video_completion_percentage`, 10–100): persistence through the
 * lesson CRUD, its validation bounds, and the `NULL` fallback that keeps the
 * legacy 90% rule (see `Lesson::effectiveVideoThreshold()`).
 */
class LessonVideoCompletionValidationTest extends TestCase
{
    private function makeCourseAndModule(): array
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();
        $module = Module::factory()->for($course)->create();

        return [$org, $course, $module];
    }

    private function videoLessonPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Lição em Vídeo',
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], $overrides);
    }

    public function test_gestor_can_create_a_video_lesson_with_a_custom_threshold(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), $this->videoLessonPayload([
            'video_completion_percentage' => 50,
        ]));

        $response->assertRedirect(route('modules.lessons.index', $module));
        $lesson = $module->lessons()->sole();
        $this->assertSame(50, $lesson->video_completion_percentage);
        $this->assertSame(0.5, $lesson->effectiveVideoThreshold());
    }

    /**
     * Bounds of the dial: below 10, above 100 and non-numeric input are all
     * rejected before anything is written.
     */
    public function test_out_of_range_or_non_numeric_thresholds_are_rejected_with_422(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        foreach ([5, 101, 'abc'] as $index => $invalidValue) {
            $title = "Lição Inválida {$index}";

            $this->from(route('modules.lessons.create', $module))
                ->post(route('modules.lessons.store', $module), $this->videoLessonPayload([
                    'title' => $title,
                    'video_completion_percentage' => $invalidValue,
                ]))
                ->assertSessionHasErrors('video_completion_percentage');

            $this->assertDatabaseMissing('lessons', ['title' => $title]);
        }
    }

    public function test_creating_a_lesson_without_the_field_lands_on_the_legacy_90_percent(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        // A video lesson created without the field (a client predating the
        // setting) never carries a threshold of its own: the key is absent
        // from the validated payload, so the column default fills the row.
        // Either way (`NULL` or 90) the accessor is the authority — and it
        // yields the legacy 90%.
        $this->post(route('modules.lessons.store', $module), $this->videoLessonPayload());

        $lesson = $module->lessons()->sole();
        $this->assertSame(90, $lesson->video_completion_percentage);
        $this->assertSame(0.9, $lesson->effectiveVideoThreshold());
    }

    /**
     * A lesson row that predates the column (or any row explicitly left
     * `NULL`) keeps the legacy rule through the accessor's fallback — the
     * column default never hides that `NULL` is a supported state.
     */
    public function test_a_null_threshold_row_keeps_the_legacy_90_percent_rule(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create([
            'video_completion_percentage' => null,
        ]);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'video_completion_percentage' => null,
        ]);
        $this->assertSame(0.9, $lesson->effectiveVideoThreshold());
    }

    public function test_gestor_can_change_the_threshold_of_an_existing_lesson(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->withThreshold(50)->create();

        $this->put(route('lessons.update', $lesson), $this->videoLessonPayload([
            'title' => $lesson->title,
            'video_completion_percentage' => 75,
        ]))->assertRedirect(route('modules.lessons.index', $module));

        $this->assertSame(75, $lesson->fresh()->video_completion_percentage);
        $this->assertSame(0.75, $lesson->fresh()->effectiveVideoThreshold());
    }

    /**
     * An update that does not send the field at all must leave the persisted
     * value untouched — a client that predates the setting never resets it.
     */
    public function test_updating_a_lesson_without_the_field_preserves_the_persisted_threshold(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->withThreshold(50)->create();

        $this->put(route('lessons.update', $lesson), $this->videoLessonPayload([
            'title' => $lesson->title,
        ]))->assertRedirect(route('modules.lessons.index', $module));

        $this->assertSame(50, $lesson->fresh()->video_completion_percentage);
    }

    /**
     * Clearing the video URL nulls every video field together (the
     * `LessonController` rule that keeps no video stamp without a URL),
     * which drops the lesson back onto the legacy 90% fallback.
     */
    public function test_clearing_the_video_url_clears_the_threshold(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->withYoutube()->withThreshold(50)->create();

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'content_text' => '<p>Agora é texto</p>',
        ])->assertRedirect(route('modules.lessons.index', $module));

        $fresh = $lesson->fresh();
        $this->assertNull($fresh->video_url);
        $this->assertNull($fresh->video_completion_percentage);
        $this->assertSame(0.9, $fresh->effectiveVideoThreshold());
    }
}
