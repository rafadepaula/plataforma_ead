<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Render contract of the trail-builder wrapper components (sortable list/
 * row, file dropzone, video field) and of the `confirmDusk` prop added to
 * `<x-ui.confirm-modal>`: the Dusk selector of the modal's confirm submit.
 *
 * These are pure rendering assertions (no HTTP), mirroring
 * `UiModalComponentTest`: they pin the DOM contract that
 * `ModuleReorder.js` (`[data-reorder-url]` / `[data-id]`), `LessonForm.js`
 * (`[data-file-drop]`, `[data-video-field]`) and the selector
 * contract (`delete-module-{id}` / `delete-lesson-{id}` on the modal submit)
 * are coded against.
 */
class UiSortableFieldComponentsTest extends TestCase
{
    public function test_sortable_list_and_row_render_the_reorder_dom_contract(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.sortable-list reorder-url="/courses/1/modules/reorder" dusk="module-list">
                <x-ui.sortable-row id="7" title="Módulo Um" dusk="module-row-7">
                    <x-slot:chips><span class="ds-chip">2 lições</span></x-slot:chips>
                    <x-slot:actions><button type="button">Lições</button></x-slot:actions>
                </x-ui.sortable-row>
            </x-ui.sortable-list>
            BLADE);

        $this->assertMatchesRegularExpression(
            '/<ul[^>]*data-reorder-url="\/courses\/1\/modules\/reorder"/',
            $html,
            'The sortable list root must be the `<ul data-reorder-url="...">` that ModuleReorder.js binds to.'
        );
        $this->assertMatchesRegularExpression(
            '/<ul[^>]*dusk="module-list"/',
            $html,
            'The dusk passed to <x-ui.sortable-list> must reach the rendered <ul>.'
        );
        $this->assertMatchesRegularExpression(
            '/<ul[^>]*class="[^"]*\bds-sortable-list\b/',
            $html,
            'The list must carry the `ds-sortable-list` design-system class.'
        );

        $this->assertMatchesRegularExpression(
            '/<li[^>]*data-id="7"[^>]*draggable="true"/',
            $html,
            'Each row must be a `<li data-id="..." draggable="true">` — the reorder payload reads data-id.'
        );
        $this->assertMatchesRegularExpression(
            '/<li[^>]*dusk="module-row-7"/',
            $html,
            'The dusk passed to <x-ui.sortable-row> must reach the rendered <li>.'
        );
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bdrag-handle\b/',
            $html,
            'The grip handle must keep the `drag-handle` class (cursor: grab styling).'
        );
        $this->assertStringContainsString('Módulo Um', $html);
        $this->assertStringContainsString('2 lições', $html);
        $this->assertStringContainsString('<button type="button">Lições</button>', $html);
        $this->assertMatchesRegularExpression(
            '/data-move-up/',
            $html,
            'Rows must ship keyboard-accessible move-up controls.'
        );
        $this->assertMatchesRegularExpression(
            '/data-move-down/',
            $html,
            'Rows must ship keyboard-accessible move-down controls.'
        );
    }

    public function test_file_drop_renders_multi_file_input_and_persisted_attachment_list(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.file-drop name="images" label="Imagem" accept="image/*" :max-size="2" dusk="lesson-image-input"
                :attachments="[['id' => 3, 'kind' => 'image', 'path' => 'orgs/1/courses/2/images/a.png', 'original_name' => 'diagrama-trilha.png', 'size_bytes' => 1536]]" />
            BLADE);

        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="file"[^>]*name="images\[\]"/',
            $html,
            'The dropzone input must post an array (name="images[]") for multi-file uploads.'
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*multiple/',
            $html,
            'The dropzone input must accept multiple files.'
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*accept="image\/\*"/',
            $html,
            'The accept prop must reach the file input.'
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*dusk="lesson-image-input"/',
            $html,
            'The dusk passed to <x-ui.file-drop> must reach the file input (Dusk `attach` target).'
        );
        $this->assertMatchesRegularExpression(
            '/<div[^>]*data-max-size="2048"[^>]*data-file-drop/',
            $html,
            'The zone must expose its client-side limit in bytes (maxSize prop in MB) for LessonForm.js validation.'
        );

        $this->assertStringContainsString('diagrama-trilha.png', $html);
        $this->assertMatchesRegularExpression(
            '/dusk="remove-file-3"/',
            $html,
            'Each persisted attachment must expose its per-file removal selector remove-file-{id}.'
        );
        $this->assertMatchesRegularExpression(
            '/1,5\s*KB/',
            $html,
            'Attachment sizes must be rendered in KB/MB.'
        );
    }

    public function test_video_field_renders_provider_select_pastel_empty_state_and_embedded_filled_state(): void
    {
        $empty = Blade::render('<x-ui.video-field dusk="lesson-video-input" />');

        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="video_provider"[^>]*dusk="lesson-provider-select"/',
            $empty,
            'The provider select must emit name=video_provider and its own dusk selector.'
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="video_url"[^>]*dusk="lesson-video-input"/',
            $empty,
            'The video URL input must keep name=video_url and receive the dusk passthrough.'
        );
        $this->assertMatchesRegularExpression(
            '/ds-video-wash/',
            $empty,
            'The empty state must render the pastel-wash block.'
        );
        $this->assertMatchesRegularExpression(
            '/ds-video-play/',
            $empty,
            'The empty state must render the circular play button.'
        );

        $filled = Blade::render(
            '<x-ui.video-field value="https://www.youtube.com/watch?v=dQw4w9WgXcQ" dusk="lesson-video-input" />'
        );

        $this->assertMatchesRegularExpression(
            '/<iframe[^>]*src="https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ"/',
            $filled,
            'A stored watch URL must render as the sanitized privacy-enhanced /embed/ iframe.'
        );
        $this->assertMatchesRegularExpression(
            '/<iframe[^>]*dusk="video-preview"/',
            $filled,
            'The preview iframe must carry dusk="video-preview".'
        );

        // Regressão: a URL canônica já salva (nocookie /embed/) precisa
        // pré-visualizar — antes o regex do campo não a reconhecia e a
        // edição de uma lição salva mostrava o estado vazio.
        $canonical = Blade::render(
            '<x-ui.video-field value="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" dusk="lesson-video-input" />'
        );

        $this->assertMatchesRegularExpression(
            '/<iframe[^>]*src="https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ"/',
            $canonical,
            'An already-sanitized nocookie embed URL must still render the preview iframe.'
        );

        $vimeo = Blade::render(
            '<x-ui.video-field value="https://vimeo.com/76979871/abcdef12345" dusk="lesson-video-input" />'
        );

        $this->assertMatchesRegularExpression(
            '/<iframe[^>]*src="https:\/\/player\.vimeo\.com\/video\/76979871\?h=abcdef12345"/',
            $vimeo,
            'An unlisted Vimeo URL must render as the player.vimeo.com embed carrying the hash.'
        );
    }

    public function test_confirm_modal_confirm_dusk_prop_replaces_the_default_submit_selector(): void
    {
        $overridden = Blade::render(
            '<x-ui.confirm-modal id="delete-module-9" title="Remover módulo" action="/modules/9" confirm-dusk="delete-module-9" form-dusk="delete-module-form-9" />'
        );

        $this->assertMatchesRegularExpression(
            '/dusk="delete-module-9"/',
            $overridden,
            'The confirmDusk prop must land on the modal confirm submit button.'
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]*dusk="delete-module-form-9"/',
            $overridden,
            'The formDusk prop must keep landing on the embedded form.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/dusk="confirm-modal-delete-module-9-confirm"/',
            $overridden,
            'The default confirm selector must yield when confirmDusk is provided.'
        );

        $default = Blade::render('<x-ui.confirm-modal id="legacy" action="/legacy" />');
        $this->assertMatchesRegularExpression(
            '/dusk="confirm-modal-legacy-confirm"/',
            $default,
            'Existing callers without confirmDusk must keep the default selector (additive prop).'
        );
    }
}
