<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BUG-003 — the shared `<x-ui.modal>` backdrop must render closed.
 *
 * Alpine.js is not installed in this project, so the component's
 * `x-data`/`x-show`/`x-cloak` attributes are inert. If the backdrop ships
 * with an inline `display: flex`, every modal is visible in the raw HTML
 * and only gets hidden once `ModalManager.hideBackdropsOnLoad()` runs after
 * first paint — which makes the modal flash on load, or stay open forever
 * when the JS bundle is stale or fails to load.
 */
class UiModalComponentTest extends TestCase
{
    public function test_modal_backdrop_renders_hidden_so_it_cannot_flash_before_javascript_runs(): void
    {
        $html = Blade::render('<x-ui.modal id="test-modal" title="Detalhes">body</x-ui.modal>');

        $this->assertStringContainsString('class="dialog-backdrop"', $html);
        $this->assertMatchesRegularExpression(
            '/class="dialog-backdrop"[^>]*style="[^"]*display:\s*none/',
            $html,
            'The <x-ui.modal> backdrop must ship with inline `display: none`; otherwise it is visible until JavaScript hides it.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="dialog-backdrop"[^>]*style="[^"]*display:\s*flex/',
            $html,
            'The <x-ui.modal> backdrop must not ship with inline `display: flex` — ModalManager.open() sets that at open time.'
        );
    }
}
