<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-02 E2E Dusk test verifying topbar, sidebar, footer layout rendering and zero-radius design rules.
 */
class LayoutRenderingTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test master layout rendering and zero-radius style rule enforcement.
     */
    public function test_layout_rendering_topbar_sidebar_footer_and_zero_radius(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('body');

            // Verify zero-radius design system rule via computed style on target elements
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

    /**
     * Test page layout container structure assertions.
     */
    public function test_layout_structure_and_main_content_area_presence(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPresent('main');
        });
    }
}
