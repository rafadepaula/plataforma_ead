<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 *  the `lesson_media` table backing multi-file lessons: the
 * legacy-column backfill migration, `Lesson::media()`/`images()`/`pdfs()`
 * relations, the `LessonFactory::media()` state, the `{N} lições` count fed
 * by `ModuleController::index()`, and the student classroom partials that
 * iterate the relation (falling back to the legacy single-file columns when
 * a lesson has no media rows).
 */
class LessonMediaTest extends TestCase
{
    /**
     *  the data migration is executed directly (it already ran,
     * against an empty `lessons` table, during RefreshDatabase's
     * `migrate:fresh`), mirroring how it will behave on production data.
     */
    private function runBackfillMigration(): void
    {
        $migration = require database_path('migrations/2026_08_23_000002_backfill_lesson_media_from_legacy_columns.php');
        $migration->up();
    }

    /**
     * Module under a fresh org-owned Course (both factories intentionally
     * require the parent context to be set explicitly).
     */
    private function makeModule(): Module
    {
        $org = Organization::factory()->create();

        return Module::factory()->for(Course::factory()->create(['org_id' => $org->id]))->create();
    }

    public function test_backfill_migration_copies_legacy_columns_into_lesson_media_rows(): void
    {
        $module = $this->makeModule();
        $lesson = Lesson::factory()->for($module)->create([
            'image_path' => 'orgs/1/courses/1/images/legacy.png',
            'pdf_path' => 'orgs/1/courses/1/pdfs/legacy.pdf',
        ]);
        $emptyLesson = Lesson::factory()->for($module)->richText()->create();

        $this->runBackfillMigration();

        $this->assertDatabaseHas('lesson_media', [
            'lesson_id' => $lesson->id,
            'kind' => 'image',
            'path' => 'orgs/1/courses/1/images/legacy.png',
            'original_name' => 'legacy.png',
        ]);
        $this->assertDatabaseHas('lesson_media', [
            'lesson_id' => $lesson->id,
            'kind' => 'pdf',
            'path' => 'orgs/1/courses/1/pdfs/legacy.pdf',
            'original_name' => 'legacy.pdf',
        ]);
        $this->assertDatabaseMissing('lesson_media', ['lesson_id' => $emptyLesson->id]);

        // legacy columns are kept for backward-compatible read paths
        $lesson->refresh();
        $this->assertSame('orgs/1/courses/1/images/legacy.png', $lesson->image_path);
        $this->assertSame('orgs/1/courses/1/pdfs/legacy.pdf', $lesson->pdf_path);
    }

    public function test_backfill_migration_is_idempotent(): void
    {
        $module = $this->makeModule();
        $lesson = Lesson::factory()->for($module)->create([
            'image_path' => 'orgs/1/courses/1/images/legacy.png',
        ]);

        $this->runBackfillMigration();
        $this->runBackfillMigration();

        $this->assertDatabaseCount('lesson_media', 1);
        $this->assertSame(1, $lesson->media()->where('kind', 'image')->count());
    }

    public function test_lesson_media_relations_partition_by_kind(): void
    {
        $lesson = Lesson::factory()->for($this->makeModule())->media(images: 2, pdfs: 3)->create();

        $this->assertSame(5, $lesson->media()->count());
        $this->assertSame(2, $lesson->images()->count());
        $this->assertSame(3, $lesson->pdfs()->count());
    }

    public function test_lesson_factory_media_state_syncs_the_legacy_columns_to_the_first_attachment(): void
    {
        $lesson = Lesson::factory()->for($this->makeModule())->media()->create();

        $lesson->refresh();
        $this->assertSame($lesson->images()->orderBy('id')->value('path'), $lesson->image_path);
        $this->assertSame($lesson->pdfs()->orderBy('id')->value('path'), $lesson->pdf_path);
    }

    public function test_modules_index_exposes_a_lessons_count_per_module(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $withTwo = Module::factory()->for($course)->create();
        Lesson::factory()->for($withTwo)->count(2)->create();
        $empty = Module::factory()->for($course)->create();

        // soft-deleted lessons must not count towards the "{N} lições" chip
        $softDeleted = Lesson::factory()->for($withTwo)->create();
        $softDeleted->delete();

        $response = $this->get(route('courses.modules.index', $course));

        $response->assertOk();
        $modules = $response->viewData('modules');
        $this->assertSame(2, $modules->firstWhere('id', $withTwo->id)->lessons_count);
        $this->assertSame(0, $modules->firstWhere('id', $empty->id)->lessons_count);
    }

    /**
     * Authenticates an enrolled ALUNO for the student classroom request.
     */
    private function actingAsEnrolledStudent(Lesson $lesson): User
    {
        $course = $lesson->module->course;
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($aluno);

        return $aluno;
    }

    public function test_student_lesson_view_renders_every_media_image(): void
    {
        Storage::fake('public');

        $lesson = Lesson::factory()->for($this->makeModule())->media(images: 2, pdfs: 0)->create(['is_published' => true]);
        $lesson->images()->get()->each(fn ($media) => Storage::disk('public')->put($media->path, 'fake-png'));
        $this->actingAsEnrolledStudent($lesson);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $lesson->images()->orderBy('id')->get()->each(function ($media, int $index) use ($response, $lesson): void {
            $suffix = $index > 0 ? "-{$index}" : '';
            $response->assertSee('src="'.Storage::url($media->path), false);
            $response->assertSee('dusk="lesson-image-'.$lesson->id.$suffix.'"', false);
        });
    }

    public function test_student_lesson_view_falls_back_to_the_legacy_image_column(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('orgs/1/courses/1/images/legacy.png', 'fake-png');

        $lesson = Lesson::factory()->for($this->makeModule())->withImage()->create([
            'image_path' => 'orgs/1/courses/1/images/legacy.png',
            'is_published' => true,
        ]);
        $this->actingAsEnrolledStudent($lesson);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('src="'.Storage::url('orgs/1/courses/1/images/legacy.png'), false);
        $response->assertSee('dusk="lesson-image-'.$lesson->id.'"', false);
    }

    public function test_student_lesson_view_renders_every_media_pdf(): void
    {
        Storage::fake('local');

        $lesson = Lesson::factory()->for($this->makeModule())->media(images: 0, pdfs: 2)->create(['is_published' => true]);
        $lesson->pdfs()->get()->each(fn ($media) => Storage::disk('local')->put($media->path, '%PDF-1.4'));
        $this->actingAsEnrolledStudent($lesson);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $lesson->pdfs()->orderBy('id')->get()->each(function ($media, int $index) use ($response, $lesson): void {
            $suffix = $index > 0 ? "-{$index}" : '';
            $response->assertSee('data-pdf-url="'.route('lessons.pdf.show', [$lesson, $index]), false);
            $response->assertSee('dusk="pdf-viewer-'.$lesson->id.$suffix.'"', false);
            $response->assertSee('dusk="pdf-mode-toggle-'.$lesson->id.$suffix.'"', false);
            $response->assertDontSee('dusk="pdf-download-'.$lesson->id.$suffix.'"', false);
        });
    }

    public function test_student_lesson_view_falls_back_to_the_legacy_pdf_column(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orgs/1/courses/1/pdfs/legacy.pdf', '%PDF-1.4');

        $lesson = Lesson::factory()->for($this->makeModule())->withPdf()->create([
            'pdf_path' => 'orgs/1/courses/1/pdfs/legacy.pdf',
            'is_published' => true,
        ]);
        $this->actingAsEnrolledStudent($lesson);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-pdf-url="'.route('lessons.pdf.show', [$lesson, 0]), false);
        $response->assertSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertSee('dusk="pdf-mode-toggle-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-download-'.$lesson->id.'"', false);
    }
}
