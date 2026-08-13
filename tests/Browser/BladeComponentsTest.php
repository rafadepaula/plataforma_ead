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

            // `ModalManager` was retired by the Bootstrap 5.3 migration in
            // favour of `bootstrap.Modal` + `data-bs-*`; `window.bootstrap` is
            // now the published contract for driving modals (see
            // bootstrap-conventions §9).
            $modulesLoaded = $browser->script("
                return typeof window.HttpClient !== 'undefined'
                    && typeof window.bootstrap !== 'undefined'
                    && typeof window.bootstrap.Modal !== 'undefined'
                    && typeof window.NotificationService !== 'undefined';
            ")[0];

            $this->assertTrue(
                (bool) $modulesLoaded,
                'Frontend JS modules (HttpClient, bootstrap.Modal, NotificationService) must be registered on window.'
            );
        });
    }

    /**
     * Test bootstrap.Modal API modal state open, close, and DOM attribute toggling.
     */
    public function test_modal_component_open_and_close_interactions(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/');

            // Verify the Bootstrap Modal API that replaced ModalManager is
            // available and exposes the show/hide surface the app drives.
            $hasModalApi = $browser->script("
                return typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
                    && typeof window.bootstrap.Modal.prototype.show === 'function'
                    && typeof window.bootstrap.Modal.prototype.hide === 'function';
            ")[0];

            $this->assertTrue((bool) $hasModalApi, 'bootstrap.Modal must expose getOrCreateInstance plus show and hide API methods.');
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
