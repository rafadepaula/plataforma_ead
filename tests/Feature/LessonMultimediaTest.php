<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UnresolvedOrgContextException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 *  Lesson multimedia CRUD: the four supported content kinds (Rich
 * Text, Imagem, PDF, Vídeo do YouTube/Vimeo), `FileUploadService`'s isolated
 * per-tenant/per-course storage path, and `VideoUrlSanitizerManager`'s
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

    public function test_gestor_can_view_the_lesson_management_screens(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->richText()->create();

        $this->get(route('modules.lessons.index', $module))
            ->assertOk()
            ->assertViewIs('modules.lessons.index')
            ->assertViewHas('lessons');

        $this->get(route('modules.lessons.create', $module))
            ->assertOk()
            ->assertViewIs('modules.lessons.create');

        $this->get(route('lessons.edit', $lesson))
            ->assertOk()
            ->assertViewIs('modules.lessons.edit');
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
            'images' => [UploadedFile::fake()->image('capa.png')],
        ]);

        $lesson = $module->lessons()->sole();
        $media = $lesson->media()->where('kind', 'image')->sole();
        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/images/", $media->path);
        Storage::disk('public')->assertExists($media->path);

        // legacy column stays in sync with the first attachment for
        // backward-compatible read paths (`classroom/lesson.blade.php` dispatch)
        $this->assertSame($media->path, $lesson->image_path);
    }

    public function test_gestor_can_upload_multiple_images_and_pdfs_in_a_single_request(): void
    {
        Storage::fake('public');
        [$org, $course, $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição Multimídia',
            'type' => 'content',
            'images' => [
                UploadedFile::fake()->image('capa.png'),
                UploadedFile::fake()->image('detalhe.png'),
            ],
            'pdfs' => [
                UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('exercicios.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('modules.lessons.index', $module));
        $lesson = $module->lessons()->sole();
        $this->assertSame(4, $lesson->media()->count());
        $this->assertSame(2, $lesson->media()->where('kind', 'image')->count());
        $this->assertSame(2, $lesson->media()->where('kind', 'pdf')->count());

        $lesson->media()->where('kind', 'image')->pluck('path')->each(function (string $path) use ($org, $course): void {
            $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/images/", $path);
            Storage::disk('public')->assertExists($path);
        });
        $lesson->media()->where('kind', 'pdf')->pluck('path')->each(function (string $path) use ($org, $course): void {
            $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/pdfs/", $path);
            Storage::disk('public')->assertExists($path);
        });

        $this->assertSame($lesson->media()->where('kind', 'image')->orderBy('id')->value('path'), $lesson->image_path);
        $this->assertSame($lesson->media()->where('kind', 'pdf')->orderBy('id')->value('path'), $lesson->pdf_path);
    }

    public function test_media_rows_keep_the_original_file_name_and_size(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com Metadados',
            'type' => 'content',
            'images' => [UploadedFile::fake()->image('capa-do-curso.png')],
        ]);

        $media = $module->lessons()->sole()->media()->sole();
        $this->assertSame('capa-do-curso.png', $media->original_name);
        $this->assertGreaterThan(0, $media->size_bytes);
    }

    public function test_an_image_larger_than_2mb_is_rejected_atomically(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com Imagem Pesada',
            'type' => 'content',
            'images' => [UploadedFile::fake()->image('gigante.png')->size(3000)],
            'pdfs' => [UploadedFile::fake()->create('valido.pdf', 100, 'application/pdf')],
        ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição com Imagem Pesada']);
        $this->assertDatabaseCount('lesson_media', 0);
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_a_pdf_larger_than_10mb_is_rejected(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com PDF Pesado',
            'type' => 'content',
            'pdfs' => [UploadedFile::fake()->create('gigante.pdf', 11000, 'application/pdf')],
        ]);

        $response->assertSessionHasErrors('pdfs.0');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição com PDF Pesado']);
        $this->assertDatabaseCount('lesson_media', 0);
    }

    public function test_a_non_pdf_file_sent_as_pdf_is_rejected(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com Falso PDF',
            'type' => 'content',
            'pdfs' => [UploadedFile::fake()->create('malicioso.txt', 10, 'text/plain')],
        ]);

        $response->assertSessionHasErrors('pdfs.0');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição com Falso PDF']);
    }

    public function test_gestor_can_create_a_pdf_lesson_stored_in_the_courses_isolated_org_path(): void
    {
        Storage::fake('public');
        [$org, $course, $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição com PDF',
            'type' => 'content',
            'pdfs' => [UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf')],
        ]);

        $lesson = $module->lessons()->sole();
        $media = $lesson->media()->where('kind', 'pdf')->sole();
        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/pdfs/", $media->path);
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($media->path, $lesson->pdf_path);
    }

    public function test_gestor_can_create_a_youtube_lesson_with_a_sanitized_embed_url(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição em Vídeo',
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $lesson = $module->lessons()->sole();
        $this->assertSame('youtube', $lesson->video_provider);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->video_url);
    }

    public function test_gestor_can_create_a_vimeo_lesson_with_a_sanitized_embed_url(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        // Provedor não selecionado: a URL do Vimeo unlisted (com hash de
        // path) basta para o servidor detectar o provedor e canonicalizar.
        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição em Vídeo Vimeo',
            'type' => 'content',
            'video_url' => 'https://vimeo.com/76979871/abcdef12345',
        ]);

        $lesson = $module->lessons()->sole();
        $this->assertSame('vimeo', $lesson->video_provider);
        $this->assertSame('https://player.vimeo.com/video/76979871?h=abcdef12345', $lesson->video_url);
    }

    public function test_a_malformed_video_url_is_rejected_with_a_validation_error(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição Inválida',
            'type' => 'content',
            'video_url' => 'https://vimeo.com/12345',
        ]);

        $response->assertSessionHasErrors('video_url');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição Inválida']);
    }

    public function test_a_javascript_uri_disguised_as_a_video_url_is_rejected(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Lição Maliciosa',
            'type' => 'content',
            'video_url' => 'javascript:alert(1)',
        ]);

        $response->assertSessionHasErrors('video_url');
        $this->assertDatabaseMissing('lessons', ['title' => 'Lição Maliciosa']);
    }

    /**
     * `StoreLessonRequest` and `UpdateLessonRequest` share the same
     * `withValidator` sanitize hook; this pins the UPDATE side of it (the
     * store side is covered above) — a non-parseable URL must fail validation
     * before `$lesson->update()` ever runs, leaving the stored value intact.
     */
    public function test_a_malformed_video_url_is_rejected_with_a_validation_error_on_update(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->richText()->create();

        $response = $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'video_url' => 'https://vimeo.com/12345',
        ]);

        $response->assertSessionHasErrors('video_url');
        $this->assertNull($lesson->fresh()->video_url);
    }

    /**
     * `FileUploadService::resolveOrgId()` falls back to `auth()`/session only
     * for Course instances built outside `OrgScope`; when nothing resolves,
     * the upload must abort loudly instead of writing into a tenant-less
     * `orgs//courses/...` path.
     */
    public function test_storing_media_for_a_course_without_a_resolvable_org_context_is_rejected(): void
    {
        $this->expectException(UnresolvedOrgContextException::class);

        app(FileUploadService::class)->storeImages(
            [UploadedFile::fake()->image('capa.png')],
            new Course,
        );
    }

    public function test_uploading_additional_images_on_update_is_additive(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create([
            'image_path' => 'orgs/1/courses/1/images/old.png',
        ]);
        $existing = $lesson->media()->create([
            'kind' => 'image',
            'path' => $lesson->image_path,
            'original_name' => 'old.png',
            'size_bytes' => 10,
        ]);
        Storage::disk('public')->put($lesson->image_path, 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'images' => [UploadedFile::fake()->image('nova.png')],
        ]);

        $lesson->refresh();
        $this->assertSame(2, $lesson->media()->where('kind', 'image')->count());
        // pre-existing attachment is NOT deleted: multi-file uploads accumulate
        Storage::disk('public')->assertExists('orgs/1/courses/1/images/old.png');
        $this->assertNotNull($existing->fresh());
        $this->assertSame('orgs/1/courses/1/images/old.png', $lesson->image_path);
    }

    public function test_removing_persisted_media_on_update_deletes_the_row_and_the_file(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create([
            'image_path' => 'orgs/1/courses/1/images/old.png',
        ]);
        $lesson->media()->create([
            'kind' => 'image',
            'path' => 'orgs/1/courses/1/images/old.png',
            'original_name' => 'old.png',
            'size_bytes' => 10,
        ]);
        Storage::disk('public')->put('orgs/1/courses/1/images/old.png', 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'removed_media' => [$lesson->media()->sole()->id],
        ]);

        $this->assertDatabaseMissing('lesson_media', ['lesson_id' => $lesson->id]);
        Storage::disk('public')->assertMissing('orgs/1/courses/1/images/old.png');
        $this->assertNull($lesson->refresh()->image_path);
    }

    public function test_removed_media_ids_belonging_to_another_lesson_are_ignored(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->richText()->create();
        $otherLesson = Lesson::factory()->for($module)->create([
            'image_path' => 'orgs/1/courses/1/images/alheia.png',
        ]);
        $otherMedia = $otherLesson->media()->create([
            'kind' => 'image',
            'path' => 'orgs/1/courses/1/images/alheia.png',
            'original_name' => 'alheia.png',
            'size_bytes' => 10,
        ]);
        Storage::disk('public')->put('orgs/1/courses/1/images/alheia.png', 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'removed_media' => [$otherMedia->id],
        ]);

        $this->assertDatabaseHas('lesson_media', ['id' => $otherMedia->id]);
        Storage::disk('public')->assertExists('orgs/1/courses/1/images/alheia.png');
        $this->assertSame('orgs/1/courses/1/images/alheia.png', $otherLesson->refresh()->image_path);
    }

    public function test_removing_every_pdf_nulls_the_legacy_pdf_path(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create([
            'pdf_path' => 'orgs/1/courses/1/pdfs/old.pdf',
        ]);
        $lesson->media()->create([
            'kind' => 'pdf',
            'path' => 'orgs/1/courses/1/pdfs/old.pdf',
            'original_name' => 'old.pdf',
            'size_bytes' => 10,
        ]);
        Storage::disk('public')->put('orgs/1/courses/1/pdfs/old.pdf', 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
            'removed_media' => [$lesson->media()->sole()->id],
        ]);

        $this->assertNull($lesson->refresh()->pdf_path);
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

    /**
     * `type = quiz` lessons hide the content fields client-side
     * (`LessonForm.js`), but the server must not require them: every
     * content field is `nullable` in the Form Request, so a quiz-type
     * submission with no `content_text`/`images`/`pdfs`/`video_url`
     * still succeeds.
     */
    public function test_gestor_can_create_a_quiz_lesson_with_no_content_fields(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $response = $this->post(route('modules.lessons.store', $module), [
            'title' => 'Avaliação do Módulo',
            'type' => 'quiz',
        ]);

        $response->assertRedirect(route('modules.lessons.index', $module));
        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Avaliação do Módulo',
            'type' => 'quiz',
        ]);
    }

    /**
     * Switching an existing content lesson's type to `quiz` (or back)
     * must not touch its already-uploaded media: the update endpoint
     * only deletes attachments explicitly listed in `removed_media[]`,
     * so a bare type change leaves every `lesson_media` row and file
     * exactly as it was.
     */
    public function test_switching_lesson_type_to_quiz_does_not_orphan_existing_media(): void
    {
        Storage::fake('public');
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'content']);
        $media = $lesson->media()->create([
            'kind' => 'image',
            'path' => 'orgs/1/courses/1/images/capa.png',
            'original_name' => 'capa.png',
            'size_bytes' => 10,
        ]);
        Storage::disk('public')->put($media->path, 'fake-contents');

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'quiz',
        ]);

        $lesson->refresh();
        $this->assertSame('quiz', $lesson->type);
        $this->assertNotNull($media->fresh());
        Storage::disk('public')->assertExists($media->path);
    }
}
