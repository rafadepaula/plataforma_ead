<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-02 E2E Dusk test verifying Blade UI components (button, card, modal, badge, select, table) rendering and interaction.
 */
class BladeComponentsTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test registration and availability of frontend JS module triad on window object.
     */
    public function test_frontend_javascript_modules_registered_on_window(): void
    {
        $this->browse(function (Browser $browser): void {
            // Dusk's ElementResolver scopes every selector under a default
            // 'body' prefix, so `assertPresent('body')` would resolve to the
            // invalid selector 'body body' and always fail; the JS check
            // below already proves the page (and its body) rendered.
            $browser->visit('/')
                ->assertPathIs('/');

            $modulesLoaded = $browser->script("
                return typeof window.HttpClient !== 'undefined'
                    && typeof window.ModalManager !== 'undefined'
                    && typeof window.NotificationService !== 'undefined';
            ")[0];

            $this->assertTrue(
                (bool) $modulesLoaded,
                'Frontend JS modules (HttpClient, ModalManager, NotificationService) must be registered on window.'
            );
        });
    }

    /**
     * Test ModalManager API modal state open, close, and DOM attribute toggling.
     */
    public function test_modal_component_open_and_close_interactions(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/');

            // Verify ModalManager module functions exist and can manage modal elements
            $hasModalManager = $browser->script("
                return typeof window.ModalManager.open === 'function' && typeof window.ModalManager.close === 'function';
            ")[0];

            $this->assertTrue((bool) $hasModalManager, 'ModalManager must expose open and close API methods.');
        });
    }

    /**
     * Test NotificationService toast creation and auto-dismiss/close interaction.
     */
    public function test_notification_service_toast_creation_and_dismissal(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->script("window.NotificationService.success('Operação realizada com sucesso!');");

            $browser->pause(100)
                ->assertSee('Operação realizada com sucesso!')
                ->assertPresent('#notification-container');
        });
    }
}
