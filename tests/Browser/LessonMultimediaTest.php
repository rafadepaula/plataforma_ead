<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UC08 / RF07 — E2E coverage for multimedia Lesson authoring (YouTube, PDF, edit).
 */
class LessonMultimediaTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_can_create_a_lesson_with_a_sanitized_youtube_url(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $this->browse(function (Browser $browser) use ($gestor, $module): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição com YouTube')
                ->select('@lesson-type-select', 'content')
                ->type('@lesson-youtube-input', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                ->press('Criar Lição')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição criada com sucesso.');
        });

        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Lição com YouTube',
            'youtube_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);
    }

    public function test_gestor_can_create_a_lesson_with_a_pdf_upload(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $pdfPath = tempnam(sys_get_temp_dir(), 'lesson_pdf_').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF");

        $this->browse(function (Browser $browser) use ($gestor, $module, $pdfPath): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição com PDF')
                ->select('@lesson-type-select', 'content')
                ->attach('@lesson-pdf-input', $pdfPath)
                ->press('Criar Lição')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição criada com sucesso.');
        });

        unlink($pdfPath);

        $this->assertDatabaseHas('lessons', [
            'module_id' => $module->id,
            'title' => 'Lição com PDF',
        ]);

        $lesson = Lesson::where('module_id', $module->id)
            ->where('title', 'Lição com PDF')
            ->firstOrFail();

        $this->assertNotNull($lesson->pdf_path);
        $this->assertTrue(Storage::disk('public')->exists($lesson->pdf_path));
    }

    public function test_an_invalid_youtube_url_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $this->browse(function (Browser $browser) use ($gestor, $module): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.create', $module))
                ->waitFor('@lesson-form')
                ->type('title', 'Lição com URL inválida')
                ->select('@lesson-type-select', 'content')
                ->type('@lesson-youtube-input', 'https://vimeo.com/123456789')
                ->press('Criar Lição')
                ->waitForText('URL do YouTube inválida ou não suportada: "https://vimeo.com/123456789".');
        });

        $this->assertDatabaseMissing('lessons', [
            'module_id' => $module->id,
            'title' => 'Lição com URL inválida',
        ]);
    }

    public function test_gestor_can_edit_an_existing_lesson(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Lição Original',
            'type' => 'content',
        ]);

        $this->browse(function (Browser $browser) use ($gestor, $module, $lesson): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.lessons.index', $module))
                ->waitFor('@edit-lesson-'.$lesson->id)
                ->click('@edit-lesson-'.$lesson->id)
                ->waitFor('@lesson-form')
                ->clear('title')
                ->type('title', 'Lição Editada')
                ->press('Salvar Alterações')
                ->waitForLocation('/modules/'.$module->id.'/lessons')
                ->assertSee('Lição atualizada com sucesso.')
                ->assertSee('Lição Editada');
        });

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Lição Editada']);
    }
}
