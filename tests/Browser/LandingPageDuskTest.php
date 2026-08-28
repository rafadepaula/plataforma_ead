<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the public Landing Page and Component Showcase.
 *
 * Agrupado por cadeia de ciclo de vida: visita inicial anônima → verificação
 * da headline do Hero, vitrine de componentes e âncora de contato → navegação
 * pelo CTA do Hero para a tela de login → retorno à Landing Page e navegação
 * pelo link do Header → abertura do modal de ajuda contextual → visita
 * com usuário autenticado.
 */
class LandingPageDuskTest extends DuskTestCase
{
    public function test_landing_page_visitor_and_showcase_lifecycle(): void
    {
        $this->browse(function (Browser $browser): void {
            // 1. Visitante acessa a Landing Page pública e verifica headline, seções e vitrine de componentes.
            $browser->visit('/')
                ->assertPathIs('/')
                ->waitFor('@landing-headline')
                ->assertSeeIn('@landing-headline', 'Capacitação técnica continuada, do jeito certo')
                ->assertPresent('@landing-login-link')
                ->assertPresent('@landing-cta-login')
                ->assertSee('Como funciona')
                ->assertSee('As telas que você vai usar')
                // Vitrine: Card de Curso
                ->assertSee('Segurança do trabalho — NR 35')
                ->assertSee('Em andamento')
                ->assertSee('62%')
                // Vitrine: Card de Certificado
                ->assertSeeIgnoringCase('Certificado emitido')
                ->assertSee('nº 9f2b7c41')
                ->assertSee('Válido')
                ->assertSee('Validação pública')
                // Vitrine: Card de Fórum
                ->assertSee('Joana Ribeiro')
                ->assertSee('Como registrar o ponto de ancoragem na prática?')
                ->assertSee('7 respostas')
                // Seção de Contato e Rodapé
                ->assertPresent('#contato')
                ->assertSee('Deseja utilizar esta plataforma em sua organização?')
                ->assertSee('Fale conosco')
                ->assertSee('Validar certificado');

            // 2. Clica no CTA primário do Hero e navega para a página de login.
            $browser->click('@landing-cta-login')
                ->waitForLocation('/login')
                ->assertPathIs('/login');

            // 3. Retorna à Landing Page e testa o link de login do Header.
            $browser->visit('/')
                ->waitFor('@landing-login-link')
                ->click('@landing-login-link')
                ->waitForLocation('/login')
                ->assertPathIs('/login');

            // 4. Retorna à Landing Page e interage com o botão de ajuda contextual.
            $browser->visit('/')
                ->waitFor('@help-button-landing')
                ->click('@help-button-landing')
                ->waitFor('@help-modal-landing')
                ->assertSeeIn('@help-modal-landing .modal-title', 'Ajuda');
        });
    }

    public function test_authenticated_user_can_visit_landing_page(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/')
                ->assertPathIs('/')
                ->waitFor('@landing-headline')
                ->assertSeeIn('@landing-headline', 'Capacitação técnica continuada, do jeito certo')
                ->assertPresent('@landing-cta-login');
        });
    }
}
