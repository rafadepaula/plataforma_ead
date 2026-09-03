<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E do visualizador de PDF customizado (canvas via pdf.js, sem iframe):
 * ciclo de vida do Aluno (stage, watermark, sem vetor de download, modal
 * 90×90, ESC devolve ao inline, conclusão manual) e previsão do Gestor
 * mesma-org sem botão de conclusão.
 */
class LessonPdfViewerDuskTest extends DuskTestCase
{
    private Course $course;

    private Module $module;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $this->module = Module::factory()->create(['course_id' => $this->course->id]);

        $this->student = User::factory()->create();
        $this->student->assignRole(RolesEnum::ALUNO->value);
        $this->course->students()->attach($this->student->id, ['enrolled_at' => now(), 'status' => 'active']);
    }

    public function test_aluno_pdf_lifecycle_without_download_vector_and_fullscreen_round_trip(): void
    {
        $path = 'lessons/dusk-viewer-'.uniqid().'.pdf';
        Storage::disk('local')->put($path, (string) file_get_contents(base_path('tests/fixtures/pdf/sample.pdf')));

        $lesson = $this->lesson([
            'title' => 'Aula em PDF Protegido',
            'type' => 'content',
            'pdf_path' => $path,
            'order_index' => 1,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($lesson): void {
                $browser->loginAs($this->student)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@pdf-viewer-'.$lesson->id)
                    ->waitFor('@pdf-stage-'.$lesson->id)
                    // Link de download ausente.
                    ->assertMissing('@pdf-download-'.$lesson->id);

                // Mini marca d'água "Curso - Aluno" embaixo de cada página
                // renderizada (sem faixa no rodapé do visualizador).
                $browser->waitUsing(10, 200, function () use ($browser, $lesson): bool {
                    return (bool) $browser->script(
                        'return document.querySelectorAll(\'[dusk="pdf-viewer-'.$lesson->id.'"] [data-pdf-page-watermark]\').length > 0;'
                    )[0];
                }, 'Cada página renderizada deve ter sua mini marca d\'água.');

                $marks = $browser->script(
                    'return Array.from(document.querySelectorAll(\'[dusk="pdf-viewer-'.$lesson->id.'"] [data-pdf-page-watermark]\')).map((el) => el.textContent);'
                )[0];

                $this->assertNotEmpty($marks);

                foreach ($marks as $mark) {
                    $this->assertSame($this->course->title.' - '.$this->student->name, $mark);
                }

                // Nenhum vetor de documento no DOM: sem `[download]`, sem iframe.
                $leaks = $browser->script(
                    'return document.querySelector(\'[dusk="pdf-viewer-'.$lesson->id.'"]\').querySelectorAll(\'[download], iframe\').length;'
                )[0];

                $this->assertSame(0, (int) $leaks, 'O visualizador não pode expor link de download nem iframe.');

                // Tela cheia: o mesmo stage abre no modal 90vw × 90vh.
                $browser->click('@pdf-mode-toggle-'.$lesson->id)
                    ->waitFor('@modal-pdf-fullscreen-'.$lesson->id);

                $rect = $browser->script(
                    'const dialog = document.querySelector(\'[dusk="modal-pdf-fullscreen-'.$lesson->id.'"] .modal-dialog\');'
                    .'const r = dialog.getBoundingClientRect();'
                    .'return {width: r.width, height: r.height, vw: window.innerWidth, vh: window.innerHeight};'
                )[0];

                $this->assertEqualsWithDelta(0.90 * $rect['vw'], $rect['width'], 0.05 * $rect['vw'], 'O modal deve ocupar ~90% da largura da viewport.');
                $this->assertEqualsWithDelta(0.90 * $rect['vh'], $rect['height'], 0.05 * $rect['vh'], 'O modal deve ocupar ~90% da altura da viewport.');

                // Aguarda a transição de entrada terminar (o foco cai para
                // dentro do modal via focus trap — sinal público de `shown`):
                // ESC/hide durante a transição é ignorado pelo Bootstrap.
                $browser->waitUsing(5, 100, function () use ($browser, $lesson): bool {
                    return (bool) $browser->script(
                        'const modal = document.querySelector(\'[dusk="modal-pdf-fullscreen-'.$lesson->id.'"]\');'
                        .'return modal && modal.contains(document.activeElement);'
                    )[0];
                }, 'O modal de tela cheia não recebeu o foco após abrir.');

                // ESC fecha o modal e devolve o stage ao inline, sem refetch.
                $browser->keys('@modal-pdf-fullscreen-'.$lesson->id.'-close', '{escape}');

                $browser->waitUsing(5, 100, function () use ($browser, $lesson): bool {
                    return (bool) $browser->script(
                        'const modal = document.querySelector(\'[dusk="modal-pdf-fullscreen-'.$lesson->id.'"]\');'
                        .'return modal && !modal.classList.contains("show") '
                        .'&& document.querySelector(\'[dusk="pdf-viewer-'.$lesson->id.'"] [dusk="pdf-stage-'.$lesson->id.'"]\') !== null;'
                    )[0];
                }, 'Fechar o modal deve devolver o stage ao inline.');

                // Conclusão manual funciona sobre o PDF protegido.
                $browser->waitFor('@mark-complete-button')
                    ->click('@mark-complete-button')
                    ->waitFor('@lesson-completed-badge')
                    ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                    ->assertMissing('@mark-complete-button');
            });
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    public function test_same_org_gestor_previews_the_pdf_without_completion_controls(): void
    {
        $path = 'lessons/dusk-gestor-'.uniqid().'.pdf';
        Storage::disk('local')->put($path, (string) file_get_contents(base_path('tests/fixtures/pdf/sample.pdf')));

        $lesson = $this->lesson([
            'title' => 'PDF para Previsão',
            'type' => 'content',
            'pdf_path' => $path,
            'order_index' => 2,
        ]);

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $this->course->org_id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        try {
            $this->browse(function (Browser $browser) use ($lesson, $gestor): void {
                $browser->loginAs($gestor)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@pdf-viewer-'.$lesson->id)
                    ->waitFor('@pdf-stage-'.$lesson->id)
                    ->assertMissing('@mark-complete-button');
            });
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Busca no documento: abre pela barra, encontra uma ocorrência por
     * página, navega com Enter/botões rolando SÓ o stage (a página não
     * pode rolar) e fecha com ESC.
     */
    public function test_aluno_can_search_within_a_multi_page_pdf(): void
    {
        $path = 'lessons/dusk-search-'.uniqid().'.pdf';
        Storage::disk('local')->put($path, $this->threePagePdf());

        $lesson = $this->lesson([
            'title' => 'PDF Pesquisável',
            'type' => 'content',
            'pdf_path' => $path,
            'order_index' => 3,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($lesson): void {
                $browser->loginAs($this->student)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@pdf-viewer-'.$lesson->id)
                    ->click('@pdf-search-'.$lesson->id)
                    ->waitFor('@pdf-search-input-'.$lesson->id)
                    ->type('@pdf-search-input-'.$lesson->id, 'Segurança');

                $this->waitForCount($browser, $lesson, '1 de 3');

                // Enter avança a ocorrência rolando SÓ o stage.
                $browser->keys('@pdf-search-input-'.$lesson->id, '{enter}');

                $this->waitForCount($browser, $lesson, '2 de 3');

                $scroll = $browser->script(
                    'return {stage: document.querySelector(\'[dusk="pdf-stage-'.$lesson->id.'"]\').scrollTop, page: window.scrollY};'
                )[0];

                $this->assertGreaterThan(0, (int) $scroll['stage'], 'Navegar ocorrências deve rolar o visualizador.');
                $this->assertSame(0, (int) $scroll['page'], 'Navegar ocorrências não pode rolar a página.');

                // Botão volta, ESC fecha a barra.
                $browser->click('@pdf-search-prev-'.$lesson->id);

                $this->waitForCount($browser, $lesson, '1 de 3');

                $browser->keys('@pdf-search-input-'.$lesson->id, '{escape}');

                $browser->waitUsing(5, 100, function () use ($browser, $lesson): bool {
                    return (bool) $browser->script(
                        'return document.querySelector(\'[dusk="pdf-viewer-'.$lesson->id.'"] [data-pdf-search-bar]\').classList.contains("d-none");'
                    )[0];
                }, 'ESC deve fechar a barra de busca.');
            });
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Rolagem manual do stage acompanha "Página N de M" e nunca vaza para
     * a página do navegador (nem no limite do stage).
     */
    public function test_scrolling_the_stage_tracks_the_current_page_and_never_scrolls_the_page(): void
    {
        $path = 'lessons/dusk-scroll-'.uniqid().'.pdf';
        Storage::disk('local')->put($path, $this->threePagePdf());

        $lesson = $this->lesson([
            'title' => 'PDF com Rolagem',
            'type' => 'content',
            'pdf_path' => $path,
            'order_index' => 4,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($lesson): void {
                $browser->loginAs($this->student)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@pdf-viewer-'.$lesson->id);

                $browser->waitUsing(10, 200, function () use ($browser, $lesson): bool {
                    return (int) $browser->script(
                        'return document.querySelectorAll(\'[dusk="pdf-stage-'.$lesson->id.'"] canvas\').length;'
                    )[0] === 3;
                }, 'As 3 páginas devem renderizar.');

                // Encadeamento de rolagem travado por CSS no stage.
                $behavior = $browser->script(
                    'const stage = document.querySelector(\'[dusk="pdf-stage-'.$lesson->id.'"]\');'
                    .'return getComputedStyle(stage).overscrollBehavior;'
                )[0];

                $this->assertSame('contain', $behavior);

                // Rola o stage até o fim: vira página 3, página parada.
                $browser->script(
                    'document.querySelector(\'[dusk="pdf-stage-'.$lesson->id.'"]\').scrollTo({top: 999999});'
                );

                $this->waitForPageLabel($browser, $lesson, 'Página 3 de 3');
                $this->assertPageNotScrolled($browser);

                // Volta ao topo: página 1, página parada.
                $browser->script(
                    'document.querySelector(\'[dusk="pdf-stage-'.$lesson->id.'"]\').scrollTo({top: 0});'
                );

                $this->waitForPageLabel($browser, $lesson, 'Página 1 de 3');
                $this->assertPageNotScrolled($browser);
            });
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    private function waitForPageLabel(Browser $browser, Lesson $lesson, string $expected): void
    {
        $browser->waitUsing(10, 200, function () use ($browser, $lesson, $expected): bool {
            $text = $browser->script(
                'return document.querySelector(\'[dusk="pdf-page-'.$lesson->id.'"]\').textContent;'
            )[0];

            return trim((string) $text) === $expected;
        }, 'O indicador deveria ler "'.$expected.'".');
    }

    private function assertPageNotScrolled(Browser $browser): void
    {
        $scrollY = $browser->script('return window.scrollY;')[0];

        $this->assertSame(0, (int) $scrollY, 'Rolar o visualizador não pode rolar a página.');
    }

    private function waitForCount(Browser $browser, Lesson $lesson, string $expected): void
    {
        $browser->waitUsing(10, 200, function () use ($browser, $lesson, $expected): bool {
            $text = $browser->script(
                'return document.querySelector(\'[dusk="pdf-search-count-'.$lesson->id.'"]\').textContent;'
            )[0];

            return trim((string) $text) === $expected;
        }, 'Contagem da busca deveria ser "'.$expected.'".');
    }

    private function threePagePdf(): string
    {
        $pages = '';

        foreach ([1, 2, 3] as $page) {
            $break = $page < 3 ? 'page-break-after: always;' : '';
            $pages .= "<div style=\"{$break}\"><h1>Segurança, página {$page}</h1><p>Texto da demonstração.</p></div>";
        }

        return Pdf::loadHTML("<html><body>{$pages}</body></html>")->output();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lesson(array $attributes): Lesson
    {
        return Lesson::factory()->create($attributes + [
            'module_id' => $this->module->id,
            'video_provider' => null,
            'video_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
        ]);
    }
}
