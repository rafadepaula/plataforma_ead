<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BUG-003 — the shared `<x-ui.modal>` must render closed.
 *
 * The modal is driven by Bootstrap 5.3's `bootstrap.Modal`, which creates the
 * backdrop element at open time and adds `.show` to the dialog; the rendered
 * server-side HTML must therefore carry `.modal` WITHOUT `.show` (a bare
 * `.modal` is `display: none` by Bootstrap's own CSS) and must be marked
 * `aria-hidden="true"`. If the markup ever ships `.show`, an inline
 * `display: flex/block`, or drops `aria-hidden`, every modal becomes visible
 * in the raw HTML and flashes on load — or stays open forever when the JS
 * bundle is stale or fails to load.
 */
class UiModalComponentTest extends TestCase
{
    public function test_modal_renders_closed_so_it_cannot_flash_before_javascript_runs(): void
    {
        $html = Blade::render('<x-ui.modal id="test-modal" title="Detalhes">body</x-ui.modal>');

        $this->assertMatchesRegularExpression(
            '/<div\s+class="modal fade"/',
            $html,
            'The <x-ui.modal> root must be a Bootstrap `.modal fade` element.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div\s+class="[^"]*\bmodal\b[^"]*\bshow\b/',
            $html,
            'The <x-ui.modal> must not ship with the `.show` class — bootstrap.Modal adds it at open time; `.modal` alone is `display: none`.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div\s+class="modal fade"[^>]*style="/',
            $html,
            'The <x-ui.modal> root must not carry any inline `style=` — visibility is owned by Bootstrap CSS, not by inline display rules.'
        );
        $this->assertMatchesRegularExpression(
            '/<div\s+class="modal fade"[^>]*\saria-hidden="true"/s',
            $html,
            'The <x-ui.modal> must ship `aria-hidden="true"` so assistive technology never announces a closed modal.'
        );
        $this->assertMatchesRegularExpression(
            '/<div\s+class="modal fade"[^>]*\stabindex="-1"/s',
            $html,
            'The <x-ui.modal> must ship `tabindex="-1"`; bootstrap.Modal requires it to move focus into the dialog on open.'
        );
    }
}
