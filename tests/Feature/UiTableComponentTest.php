<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiTableComponentTest extends TestCase
{
    public function test_table_renders_the_design_system_structure_and_preserves_semantic_attributes(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.table striped hoverable responsive size="sm"
                        class="report-table"
                        aria-label="Relatório de cursos"
                        dusk="courses-table">
                <x-slot:toolbar><h2>Cursos</h2></x-slot:toolbar>
                <x-slot:header><tr><th scope="col">Curso</th></tr></x-slot:header>
                <tr><td data-label="Curso">Laravel</td></tr>
                <x-slot:footer><tr><td>Total: 1</td></tr></x-slot:footer>
            </x-ui.table>
        BLADE);

        $xpath = $this->xpathFor($html);
        $wrappers = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ds-table-wrap ")]');
        $this->assertNotFalse($wrappers);
        $this->assertCount(1, $wrappers);

        $wrapper = $wrappers->item(0);
        $this->assertInstanceOf(DOMElement::class, $wrapper);

        $toolbars = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ds-table-toolbar ") and contains(normalize-space(.), "Cursos")]', $wrapper);
        $tables = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ds-table-scroll ") and contains(concat(" ", normalize-space(@class), " "), " table-responsive ")]/table', $wrapper);
        $this->assertNotFalse($toolbars);
        $this->assertNotFalse($tables);
        $this->assertCount(1, $toolbars);
        $this->assertCount(1, $tables);

        $table = $tables->item(0);
        $this->assertInstanceOf(DOMElement::class, $table);
        $tableClasses = $this->classTokens($table);

        $this->assertContains('ds-table', $tableClasses);
        $this->assertContains('ds-table-sm', $tableClasses);
        $this->assertContains('ds-table-hover', $tableClasses);
        $this->assertContains('ds-table-striped', $tableClasses);
        $this->assertContains('report-table', $tableClasses);
        $this->assertSame('Relatório de cursos', $table->getAttribute('aria-label'));
        $this->assertSame('courses-table', $table->getAttribute('dusk'));
        $this->assertFalse($wrapper->hasAttribute('aria-label'));
        $this->assertFalse($wrapper->hasAttribute('dusk'));

        $this->assertSame(1, $xpath->query('./thead//th[@scope="col" and contains(normalize-space(.), "Curso")]', $table)?->count());
        $this->assertSame(1, $xpath->query('./tbody//td[@data-label="Curso" and contains(normalize-space(.), "Laravel")]', $table)?->count());
        $this->assertSame(1, $xpath->query('./tfoot//td[contains(normalize-space(.), "Total: 1")]', $table)?->count());
    }

    public function test_data_table_is_a_compatibility_alias_for_the_canonical_table(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.data-table :headers="['Aluno']" hover aria-label="Alunos">
                <tr><td data-label="Aluno">Ana</td></tr>
            </x-ui.data-table>
        BLADE);

        $xpath = $this->xpathFor($html);
        $wrappers = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ds-table-wrap ")]');
        $tables = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ds-table-scroll ") and contains(concat(" ", normalize-space(@class), " "), " table-responsive ")]/table');
        $this->assertNotFalse($wrappers);
        $this->assertNotFalse($tables);
        $this->assertCount(1, $wrappers);
        $this->assertCount(1, $tables);

        $table = $tables->item(0);
        $this->assertInstanceOf(DOMElement::class, $table);
        $tableClasses = $this->classTokens($table);

        $this->assertContains('ds-table', $tableClasses);
        $this->assertContains('ds-table-hover', $tableClasses);
        $this->assertSame('Alunos', $table->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('./thead//th[@scope="col" and normalize-space(.)="Aluno"]', $table)?->count());
        $this->assertSame(1, $xpath->query('./tbody//td[@data-label="Aluno" and normalize-space(.)="Ana"]', $table)?->count());
    }

    public function test_table_styles_use_tokens_and_enable_single_markup_card_reflow(): void
    {
        $scss = file_get_contents(resource_path('scss/components/_table.scss'));

        $this->assertIsString($scss);
        $this->assertStringContainsString('background: var(--surface);', $scss);
        $this->assertStringContainsString('padding: var(--pad-cell-y) var(--pad-cell-x);', $scss);
        $this->assertStringContainsString('background: var(--state-hover);', $scss);
        $this->assertStringContainsString('content: attr(data-label);', $scss);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums;', $scss);
        $this->assertStringNotContainsString('#e2e7f0', $scss);
    }

    public function test_pagination_is_aligned_to_the_right_outside_the_table_card(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 20, 10, 1);
        $html = Blade::render('<x-ui.pagination :paginator="$paginator" />', compact('paginator'));

        $xpath = $this->xpathFor($html);
        $navigations = $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " ds-pagination ")]');
        $this->assertNotFalse($navigations);
        $this->assertCount(1, $navigations);

        $navigation = $navigations->item(0);
        $this->assertInstanceOf(DOMElement::class, $navigation);
        $navigationClasses = $this->classTokens($navigation);
        $this->assertContains('justify-content-end', $navigationClasses);
        $this->assertNotContains('justify-content-center', $navigationClasses);
    }

    private function xpathFor(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        return new DOMXPath($document);
    }

    /**
     * @return list<string>
     */
    private function classTokens(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
