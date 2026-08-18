<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for multimedia Lesson authoring (YouTube, PDF,
 * edit).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * autoria de lições do Gestor (criar com YouTube → criar com PDF → editar)
 * é uma jornada única; a rejeição de URL inválida é negativa isolada.
 */
class LessonMultimediaTest extends DuskTestCase
{
    public function test_gestor_lesson_authoring_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $pdfPath = tempnam(sys_get_temp_dir(), 'lesson_pdf_').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF");

        try {
            $this->browse(function (Browser $browser) use ($gestor, $module, $pdfPath): void {
                // 1. Lição com URL do YouTube — persistida já sanitizada para
                //    a forma `/embed/`.
                $browser->loginAs($gestor)
                    ->visit(route('modules.lessons.create', $module))
                    ->waitFor('@lesson-form')
                    ->type('title', 'Lição com YouTube')
                    ->select('@lesson-type-select', 'content')
                    ->type('@lesson-youtube-input', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                    ->press('Criar Lição')
                    ->waitForLocation('/modules/'.$module->id.'/lessons')
                    ->assertSee('Lição criada com sucesso.')
                    ->assertSee('Lição com YouTube');

                $this->assertDatabaseHas('lessons', [
                    'module_id' => $module->id,
                    'title' => 'Lição com YouTube',
                    'youtube_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                ]);

                // 2. Lição com upload de PDF, na mesma sessão.
                $browser->visit(route('modules.lessons.create', $module))
                    ->waitFor('@lesson-form')
                    ->type('title', 'Lição com PDF')
                    ->select('@lesson-type-select', 'content')
                    ->attach('@lesson-pdf-input', $pdfPath)
                    ->press('Criar Lição')
                    ->waitForLocation('/modules/'.$module->id.'/lessons')
                    ->assertSee('Lição criada com sucesso.')
                    ->assertSee('Lição com PDF');

                $pdfLesson = Lesson::where('module_id', $module->id)
                    ->where('title', 'Lição com PDF')
                    ->firstOrFail();

                $this->assertNotNull($pdfLesson->pdf_path);
                $this->assertTrue(Storage::disk('public')->exists($pdfLesson->pdf_path));

                // 3. Edição, a partir da listagem, da lição de PDF.
                //
                //    A lição de YouTube renderiza um `<iframe>` de
                //    pré-visualização no formulário de edição; esperar o
                //    redirect logo após o submit disputa com o carregamento
                //    desse frame externo. A lição de PDF exercita exatamente
                //    o mesmo caminho de atualização sem essa dependência de
                //    rede.
                $browser->visit(route('modules.lessons.index', $module))
                    ->waitFor('@edit-lesson-'.$pdfLesson->id)
                    ->click('@edit-lesson-'.$pdfLesson->id)
                    ->waitFor('@lesson-form')
                    ->clear('title')
                    ->type('title', 'Lição Editada')
                    ->press('Salvar Alterações')
                    ->waitForText('Lição atualizada com sucesso.')
                    ->assertSee('Lição Editada');

                $this->assertDatabaseHas('lessons', [
                    'id' => $pdfLesson->id,
                    'title' => 'Lição Editada',
                ]);
            });
        } finally {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
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
}
