<?php

namespace Tests\Feature;

use App\Models\Course;
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

    public function test_pagination_shows_the_result_counter_and_links_only_when_there_are_multiple_pages(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 24, 10, 1);
        $html = Blade::render('<x-ui.pagination :paginator="$paginator" item-label="cursos" />', compact('paginator'));

        $xpath = $this->xpathFor($html);
        $navigations = $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " ds-pagination ")]');
        $this->assertNotFalse($navigations);
        $this->assertCount(1, $navigations);

        $navigation = $navigations->item(0);
        $this->assertInstanceOf(DOMElement::class, $navigation);
        $navigationClasses = $this->classTokens($navigation);
        $this->assertContains('justify-content-between', $navigationClasses);
        $this->assertNotContains('justify-content-center', $navigationClasses);
        $this->assertSame(1, $xpath->query('.//*[normalize-space(.)="Mostrando 1–10 de 24 cursos"]', $navigation)?->count());
        $this->assertSame(0, $xpath->query('.//nav', $navigation)?->count());
        $this->assertSame(2, $xpath->query('.//ul[contains(concat(" ", normalize-space(@class), " "), " pagination ")]', $navigation)?->count());
        $this->assertSame(1, $xpath->query('.//ul[contains(concat(" ", normalize-space(@class), " "), " d-sm-none ") and not(.//*[@data-page])]', $navigation)?->count());
        $this->assertSame(1, $xpath->query('.//ul[contains(concat(" ", normalize-space(@class), " "), " d-none ") and contains(concat(" ", normalize-space(@class), " "), " d-sm-flex ") and .//a[@data-page="2"]]', $navigation)?->count());
        $this->assertSame(1, $xpath->query('.//a[@data-page="2" and normalize-space(.)="2"]', $navigation)?->count());
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringNotContainsString('results', $html);

        $singlePagePaginator = new LengthAwarePaginator(range(1, 4), 4, 10, 1);
        $singlePageHtml = Blade::render('<x-ui.pagination :paginator="$singlePagePaginator" item-label="cursos" />', compact('singlePagePaginator'));

        $this->assertSame('', trim($singlePageHtml));
    }

    public function test_course_catalog_columns_and_pagination_controls_follow_the_visual_contract(): void
    {
        $courseStyles = file_get_contents(resource_path('scss/components/_courses.scss'));
        $componentIndex = file_get_contents(resource_path('scss/components/_index.scss'));
        $appStyles = file_get_contents(resource_path('scss/app.scss'));
        $paginationStyles = file_get_contents(resource_path('scss/components/_pastel-wash.scss'));

        $this->assertIsString($courseStyles);
        $this->assertMatchesRegularExpression('/\.course-catalog-workload-column\s*\{\s*width:\s*150px;\s*\}/', $courseStyles);
        $this->assertMatchesRegularExpression('/\.course-catalog-students-column\s*\{\s*width:\s*110px;\s*text-align:\s*right;\s*\}/', $courseStyles);
        $this->assertMatchesRegularExpression('/\.course-catalog-status-column\s*\{\s*width:\s*130px;\s*\}/', $courseStyles);
        $this->assertMatchesRegularExpression('/\.course-catalog-actions-column\s*\{\s*width:\s*470px;\s*text-align:\s*right;\s*\}/', $courseStyles);

        $this->assertIsString($componentIndex);
        $this->assertMatchesRegularExpression('/@import\s+["\']courses["\'];/', $componentIndex);
        $this->assertMatchesRegularExpression('/@import\s+["\']pastel-wash["\'];/', $componentIndex);

        $this->assertIsString($appStyles);
        $this->assertMatchesRegularExpression('/@import\s+["\']components\/index["\'];/', $appStyles);

        $this->assertIsString($paginationStyles);
        $this->assertMatchesRegularExpression(
            '/\.ds-pagination\s*\{[\s\S]*?\.page-link\s*\{[\s\S]*?width:\s*40px;[\s\S]*?height:\s*40px;[\s\S]*?border-radius:\s*var\(--radius-pill\);[\s\S]*?\}/',
            $paginationStyles,
        );
        $this->assertStringNotContainsString('!important', $paginationStyles);
    }

    public function test_course_title_cell_renders_exact_module_and_lesson_pluralization(): void
    {
        $emptyCourse = $this->courseWithCounts(modules: 0, lessons: 0);
        $singularCourse = $this->courseWithCounts(modules: 1, lessons: 1);
        $pluralCourse = $this->courseWithCounts(modules: 2, lessons: 3);

        $emptyHtml = Blade::render('<x-course.title-cell :course="$course" />', ['course' => $emptyCourse]);
        $singularHtml = Blade::render('<x-course.title-cell :course="$course" />', ['course' => $singularCourse]);
        $pluralHtml = Blade::render('<x-course.title-cell :course="$course" />', ['course' => $pluralCourse]);

        $this->assertStringContainsString('Sem módulos cadastrados', $emptyHtml);
        $this->assertStringNotContainsString('0 módulos', $emptyHtml);
        $this->assertStringContainsString('1 módulo · 1 aula', $singularHtml);
        $this->assertStringContainsString('2 módulos · 3 aulas', $pluralHtml);
    }

    public function test_course_row_actions_disable_removal_and_explain_active_enrollments(): void
    {
        $singularCourse = $this->courseWithCounts(activeStudents: 1);
        $pluralCourse = $this->courseWithCounts(activeStudents: 2);

        $singularHtml = Blade::render('<x-course.row-actions :course="$course" />', ['course' => $singularCourse]);
        $pluralHtml = Blade::render('<x-course.row-actions :course="$course" />', ['course' => $pluralCourse]);
        $xpath = $this->xpathFor($pluralHtml);

        $this->assertSame(1, $xpath->query('//a[@dusk="manage-modules-42" and contains(@class, "btn-tonal")]')?->count());
        $this->assertSame(1, $xpath->query('//a[@dusk="manage-completion-rules-42" and contains(@class, "btn-ghost")]')?->count());
        $this->assertSame(1, $xpath->query('//a[@dusk="edit-course-42" and contains(@class, "btn-ghost")]')?->count());
        $this->assertSame(1, $xpath->query('//button[@dusk="delete-course-42" and @disabled]')?->count());
        $this->assertSame(0, $xpath->query('//*[@data-bs-target="#delete-course-42"]')?->count());
        $this->assertStringContainsString('1 aluno matriculado', $singularHtml);
        $this->assertStringContainsString('2 alunos matriculados', $pluralHtml);
    }

    public function test_course_row_actions_enable_removal_without_an_enrollment_warning(): void
    {
        $course = $this->courseWithCounts(activeStudents: 0);

        $html = Blade::render('<x-course.row-actions :course="$course" />', compact('course'));
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//button[@dusk="delete-course-42" and @data-bs-toggle="modal" and @data-bs-target="#delete-course-42" and not(@disabled)]')?->count());
        $this->assertStringNotContainsString('matriculado', $html);
    }

    private function courseWithCounts(int $modules = 2, int $lessons = 3, int $activeStudents = 0): Course
    {
        $course = new Course;
        $course->forceFill([
            'id' => 42,
            'title' => 'Curso Laravel',
            'modules_count' => $modules,
            'lessons_count' => $lessons,
            'active_students_count' => $activeStudents,
        ]);

        return $course;
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
