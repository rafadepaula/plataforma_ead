<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RF07 — Lesson multimedia CRUD: the four supported content kinds (Rich
 * Text, Imagem, PDF, Vídeo do YouTube), `FileUploadService`'s isolated
 * per-tenant/per-course storage path, and `YoutubeSanitizerService`'s
 * embed sanitization (including XSS/embed-injection rejection).
 */
class LessonMultimediaTest extends TestCase
{
    private function makeCourseAndModule(): array
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        return [$org, $course, $module];
    }

    public function test_gestor_can_create_a_rich_text_lesson(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição de Texto',
            'type' => 'content',
            'content_text' => '<p>Conteúdo em texto</p>',
        ]);

        $response->assertRedirect(route('modules.lessons.index', $module));
        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Lição de Texto',
            'content_text' => '<p>Conteúdo em texto</p>',
        ]);
    }

    public function test_gestor_can_create_an_image_lesson_stored_in_the_courses_isolated_org_path(): void
    {
        Storage::fake('public');
        [$org, $course, $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com Imagem',
            'type' => 'content',
            'image' => UploadedFile::fake()->image('capa.png'),
        ]);

        $lesson = $module->lessons()->sole();
        $this->assertNotNull($lesson->image_path);
        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/images/", $lesson->image_path);
        Storage::disk('public')->assertExists($lesson->image_path);
    }

    public function test_gestor_can_create_a_pdf_lesson_stored_in_the_courses_isolated_org_path(): void
    {
        Storage::fake('public');
        [$org, $course, $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com PDF',
            'type' => 'content',
            'pdf' => UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf'),
        ]);

        $lesson = $module->lessons()->sole();
        $this->assertNotNull($lesson->pdf_path);
        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/pdfs/", $lesson->pdf_path);
        Storage::disk('public')->assertExists($lesson->pdf_path);
    }

    public function test_gestor_can_create_a_youtube_lesson_with_a_sanitized_embed_url(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição em Vídeo',
            'type' => 'content',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $lesson = $module->lessons()->sole();
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $lesson->youtube_url);
    }

    public function test_a_malformed_youtube_url_is_rejected_with_a_validation_error(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição Inválida',
            'type' => 'content',
            'youtube_url' => 'https://vimeo.com/12345',
        ]);

        $response->assertSessionHasErrors('youtube_url');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição Inválida']);
    }

    public function test_a_javascript_uri_disguised_as_a_youtube_url_is_rejected(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição Maliciosa',
            'type' => 'content',
            'youtube_url' => 'javascript:alert(1)',
        ]);

        $response->assertSessionHasErrors('youtube_url');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição Maliciosa']);
    }

    public function test_replacing_an_image_on_update_deletes_the_old_file(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->withImage()->create([
            'image_path' => 'orgs/1/courses/1/images/old.png',
        ]);
        Storage::disk('public')->put($lesson->image_path, 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'image' => UploadedFile::fake()->image('nova.png'),
        ]);

        $lesson->refresh();
        Storage::disk('public')->assertMissing('orgs/1/courses/1/images/old.png');
        Storage::disk('public')->assertExists($lesson->image_path);
    }

    public function test_aluno_cannot_create_a_lesson(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Proibida',
            'type' => 'content',
        ])->assertForbidden();
    }

    public function test_gestor_is_forbidden_from_creating_a_lesson_in_another_orgs_module(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $otherModule = Module::factory()->for($otherCourse)->create();
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->post(route('modules.lessons.store', $otherModule), [
            'title' => 'Invasão',
            'type' => 'content',
        ])->assertForbidden();
    }

    public function test_gestor_is_forbidden_from_managing_lessons_of_another_orgs_module_by_guessing_the_id(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $otherModule = $otherCourse->modules()->create(['title' => 'Módulo Alheio', 'order_index' => 0]);
        $otherLesson = Lesson::factory()->for($otherModule)->richText()->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->get(route('modules.lessons.index', $otherModule))->assertForbidden();
        $this->get(route('lessons.edit', $otherLesson))->assertForbidden();
        $this->put(route('lessons.update', $otherLesson), [
            'title' => 'Invasão',
            'type' => 'content',
        ])->assertForbidden();
        $this->delete(route('lessons.destroy', $otherLesson))->assertForbidden();
        $this->assertDatabaseHas('lessons', ['id' => $otherLesson->id, 'deleted_at' => null]);
    }

    /**
     * Soft-deleting a Lesson (`deleted_at`) must never take its `lesson_progress`
     * history down with it. The FK is `cascadeOnDelete()` at the DB level,
     * but that only fires on a real `DELETE`, which a soft delete never
     * issues (it's an `UPDATE ... SET deleted_at = ...`).
     */
    public function test_soft_deleting_a_lesson_preserves_its_lesson_progress_rows(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->richText()->create();
        $student = User::factory()->create();

        $progress = LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $this->delete(route('lessons.destroy', $lesson));

        $this->assertSoftDeleted($lesson);
        $this->assertDatabaseHas('lesson_progress', ['id' => $progress->id]);
    }
}
