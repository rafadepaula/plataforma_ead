<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-02 — E2E Dusk dos contratos de frontend publicados no `window`:
 * módulos JS registrados e a API do `bootstrap.Modal` que substituiu o
 * `ModalManager` na migração Bootstrap 5.3, mais a criação real de toast
 * pelo `NotificationService`.
 *
 * As inspeções estáticas de `window` compartilham uma única carga de página
 * (ver `testing-conventions`).
 */
class BladeComponentsTest extends DuskTestCase
{
    public function test_frontend_javascript_modules_and_modal_api_contracts(): void
    {
        $this->browse(function (Browser $browser): void {
            // Dusk's ElementResolver scopes every selector under a default
            // 'body' prefix, so `assertPresent('body')` would resolve to the
            // invalid selector 'body body' and always fail; the JS checks
            // below already prove the page (and its body) rendered.
            $browser->visit('/')
                ->assertPathIs('/');

            // 1. `ModalManager` was retired by the Bootstrap 5.3 migration in
            //    favour of `bootstrap.Modal` + `data-bs-*`; `window.bootstrap`
            //    is now the published contract for driving modals (see
            //    bootstrap-conventions §9).
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

            // 2. Mesma página: a superfície show/hide que o app dirige.
            $hasModalApi = $browser->script("
                return typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
                    && typeof window.bootstrap.Modal.prototype.show === 'function'
                    && typeof window.bootstrap.Modal.prototype.hide === 'function';
            ")[0];

            $this->assertTrue(
                (bool) $hasModalApi,
                'bootstrap.Modal must expose getOrCreateInstance plus show and hide API methods.'
            );
        });
    }

    public function test_notification_service_toast_creation(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->script("window.NotificationService.success('Operação realizada com sucesso!');");

            // Espera explícita pelo toast renderizado — nunca `pause()`.
            $browser->waitForText('Operação realizada com sucesso!')
                ->assertSee('Operação realizada com sucesso!')
                ->assertPresent('#notification-container');
        });
    }
}
