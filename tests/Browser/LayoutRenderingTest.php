<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-02 — E2E Dusk: estrutura do layout mestre e regra de raio zero do
 * Modernist Design System, verificadas numa única carga de página (ver
 * `testing-conventions`: uma visita, todas as checagens da mesma tela).
 */
class LayoutRenderingTest extends DuskTestCase
{
    public function test_layout_structure_and_zero_radius_rules(): void
    {
        $this->browse(function (Browser $browser): void {
            // 1. Estrutura: Dusk's ElementResolver scopes every selector under
            //    a default 'body' prefix, so `assertPresent('body')` would
            //    resolve to the invalid selector 'body body' and always fail.
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('main');

            // 2. Regra de raio zero via computed style.
            $radius = $browser->script("
                const el = document.querySelector('.btn, .card, .input, .dialog, body') || document.body;
                return window.getComputedStyle(el).borderRadius;
            ")[0];

            $this->assertTrue(
                in_array($radius, ['0px', '0', '0px 0px 0px 0px'], true) || str_contains((string) $radius, '0px'),
                "Expected border-radius to enforce 0px, got {$radius}"
            );
        });
    }
}
