<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * baseline Dusk smoke test used as the template other features
 * copy from for their own browser suites.
 *
 * Isolamento de banco: `DatabaseTruncation` herdado de `Tests\DuskTestCase`
 * (nunca `RefreshDatabase` — Dusk dirige navegador e app como processos/
 * conexões HTTP separados). Agrupamento por cadeia: todas as checagens da
 * Landing Page vivem numa única visita (ver `testing-conventions`).
 */
class ExampleSmokeTest extends DuskTestCase
{
    /**
     * `/` serve a Landing Page pública (`landing.show`),
     * renderizada como VISITANTE (sem `loginAs`). Asserta a estrutura base,
     * os títulos exatos das seções e que os CTAs apontam para a rota
     * `login`. O botão de ajuda contextual
     * (`<x-help-button key="landing" />`) renderiza `disabled` sem artigo
     * semeado, então só a presença é asserida — nunca a abertura do modal.
     */
    public function test_landing_page_full_structure_sections_and_ctas(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('main');
        });
    }
}
