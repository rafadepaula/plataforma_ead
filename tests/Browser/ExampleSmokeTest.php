<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-00 §5 — baseline Dusk smoke test used as the template other specs
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
     * SPEC-11 / RF11 — `/` serve a Landing Page pública (`landing.show`),
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
                ->assertPresent('main')
                ->assertSeeIn('h1', 'Capacitação técnica continuada, do jeito certo')
                ->assertVisible('@landing-headline')
                ->assertVisible('@landing-login-link')
                ->assertVisible('@landing-cta-login')
                ->assertPresent('@help-button-landing')
                ->assertAttribute('@landing-login-link', 'href', route('login'))
                ->assertAttribute('@landing-cta-login', 'href', route('login'))
                ->assertSee('Cursos e Trilhas')
                ->assertSee('Provas Interativas')
                ->assertSee('Certificados Oficiais')
                ->assertSee('Recebeu um convite?');
        });
    }

    public function test_dusk_can_authenticate_a_user_and_persist_the_session(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/')
                ->assertAuthenticatedAs($user);
        });
    }
}
