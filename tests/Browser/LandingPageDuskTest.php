<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\HomePage;
use Tests\DuskTestCase;

/**
 * E2E coverage of the public Landing Page and Component Showcase.
 *
 * Agrupado por cadeia de ciclo de vida: visita inicial anônima → verificação
 * da headline do Hero, vitrine de componentes e âncora de contato → navegação
 * pelo CTA do Hero para a tela de login → retorno à Landing Page e navegação
 * pelo link do Header → abertura do modal de ajuda contextual → visita
 * com usuário autenticado.
 *
 * O contrato responsivo é verificado no terceiro método: em 320/375/768/1024/1440
 * a página não pode gerar scroll horizontal, os grids colapsam conforme os
 * breakpoints de `_public-pages.scss` (1 coluna abaixo de 905px, grid de 4
 * com 2 colunas entre 905px e 1239px), o header público baixa para 64px, o
 * nome da marca sai de cena, o rodapé centraliza — e o raio de 36px do Hero
 * sobrevive no desktop.
 */
class LandingPageDuskTest extends DuskTestCase
{
    /**
     * Larguras do guardrail responsivo do projeto (mobile → desktop full HD).
     *
     * @var array<int, int>
     */
    private const WIDTHS = [320, 375, 768, 1024, 1440];

    /**
     * Viewport restaurado ao fim de cada cadeia mobile — a instância de Browser
     * é compartilhada entre os métodos da classe e a largura vazaria para o
     * teste seguinte (ver diretriz de responsividade em tests/Browser/Theme).
     *
     * @var array{0: int, 1: int}
     */
    private const DESKTOP_VIEWPORT = [1920, 1080];

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

    /**
     * Contrato responsivo da Landing Page, lido do CSS computado em vez de
     * screenshots: sem scroll horizontal em nenhuma largura, colapso dos
     * grids nos breakpoints de `_public-pages.scss`, header público de 64px,
     * marca escondida e texto do rodapé centralizado no mobile, e o raio de
     * 36px do Hero preservado no desktop.
     */
    public function test_landing_page_responsive_contract_at_every_breakpoint(): void
    {
        $this->browse(function (Browser $browser): void {
            // 1. Visitante anônimo abre a Landing Page através do page object.
            $browser->visit('/')
                ->on(new HomePage)
                ->waitFor('@headline')
                ->assertPresent('@ctaLogin')
                ->assertPresent('@loginLink')
                ->assertPresent('@contact');

            // 2. Desktop: o cartão do Hero mantém o raio de 36px (--radius-2xl).
            self::assertSame(
                '36px',
                $this->computedStyle($browser, '.landing-hero', 'borderRadius'),
                'O cartão do Hero deve preservar o raio de 36px (--radius-2xl) no desktop.'
            );

            // 3. Em cada largura: sem scroll horizontal e colapsos de grid/header/rodapé.
            foreach (self::WIDTHS as $width) {
                $browser->resize($width, 900);

                $layout = $this->landingLayout($browser);

                self::assertLessThanOrEqual(
                    $layout['innerWidth'],
                    $layout['scrollWidth'],
                    "Scroll horizontal detectado em {$width}px na Landing Page."
                );

                $isMobile = $width < 905;

                self::assertSame(
                    array_fill(0, 2, $isMobile ? 1 : 3),
                    $layout['grid3Tracks'],
                    'Os grids de 3 colunas devem renderizar '.($isMobile ? 1 : 3)." coluna(s) em {$width}px."
                );

                self::assertSame(
                    [$isMobile ? 1 : ($width < 1240 ? 2 : 4)],
                    $layout['grid4Tracks'],
                    "O grid de 4 colunas deve colapsar conforme o breakpoint em {$width}px."
                );

                self::assertSame(
                    $isMobile ? 64 : 76,
                    $layout['headerHeight'],
                    'O header público deve ter '.($isMobile ? 64 : 76)."px em {$width}px."
                );

                if ($isMobile) {
                    self::assertSame(
                        'none',
                        $layout['brandDisplay'],
                        "O nome da marca deve sair de cena abaixo de 905px (medido em {$width}px)."
                    );

                    // O rodapé centraliza pelos dois sinais da media query de
                    // `_public-pages.scss`. O `justify-content` só é medível
                    // porque o markup não carrega mais o utilitário
                    // `justify-content-between` do Bootstrap, cujo `!important`
                    // vencia a declaração normal e deixava a regra mobile morta.
                    self::assertSame(
                        'center',
                        $layout['footerTextAlign'],
                        "O rodapé deve centralizar o texto abaixo de 905px (medido em {$width}px)."
                    );

                    self::assertSame(
                        'center',
                        $layout['footerJustify'],
                        "O rodapé deve centralizar o conteúdo abaixo de 905px (medido em {$width}px)."
                    );

                    continue;
                }

                self::assertNotSame(
                    'none',
                    $layout['brandDisplay'],
                    "O nome da marca deve permanecer visível a partir de 905px (medido em {$width}px)."
                );

                self::assertSame(
                    'space-between',
                    $layout['footerJustify'],
                    "O rodapé deve distribuir o conteúdo entre as bordas no desktop (medido em {$width}px)."
                );
            }

            // 4. Restaura o viewport: a instância de Browser é compartilhada
            //    entre os métodos da classe.
            $browser->resize(...self::DESKTOP_VIEWPORT);
        });
    }

    /**
     * As cinco faixas alternam lavagem azul e superfície branca, e a faixa da
     * vitrine fica em `--surface` (branco puro), não em `--surface-alt`.
     *
     * A alternância é a única prova visual de que a faixa da vitrine seguiu a
     * especificação da tela em vez do deck de design, que pedia o cinza
     * `--surface-alt` ali; sem esta asserção a escolha ficaria só no SCSS e
     * uma troca de token passaria despercebida pela suíte.
     */
    public function test_landing_bands_alternate_between_blue_wash_and_plain_surface(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->on(new HomePage)
                ->waitFor('@headline');

            /** @var array{bands: array<int, string>, surface: string, page: string, successContainer: string, finalStep: string, plainStep: string} $palette */
            $palette = $browser->script(<<<'JS'
                const probe = document.createElement('div');
                probe.style.background = 'var(--surface)';
                document.body.appendChild(probe);
                const surface = getComputedStyle(probe).backgroundColor;
                probe.style.background = 'var(--success-container)';
                const successContainer = getComputedStyle(probe).backgroundColor;
                probe.remove();

                return {
                    bands: Array.from(document.querySelectorAll('.landing-band'))
                        .map((band) => getComputedStyle(band).backgroundColor),
                    surface: surface,
                    page: getComputedStyle(document.body).backgroundColor,
                    successContainer: successContainer,
                    finalStep: getComputedStyle(document.querySelector('.landing-step--success')).backgroundColor,
                    plainStep: getComputedStyle(document.querySelector('.landing-step:not(.landing-step--success)')).backgroundColor,
                };
            JS)[0];

            $bands = $palette['bands'];

            self::assertCount(5, $bands, 'A Landing Page deve pintar exatamente cinco faixas.');

            [$hero, $pillars, $process, $showcase, $contact] = $bands;

            self::assertSame($hero, $process, 'Hero e "Como funciona" compartilham a lavagem azul.');
            self::assertSame($hero, $contact, 'A faixa de contato fecha a página com a mesma lavagem azul.');

            self::assertSame(
                $palette['surface'],
                $showcase,
                'A faixa da vitrine deve usar --surface (branco), e não o cinza --surface-alt do deck de design.'
            );
            // A faixa dos pilares não pinta fundo próprio: ela deixa a
            // superfície da página aparecer, que é a mesma da vitrine.
            self::assertSame(
                'rgba(0, 0, 0, 0)',
                $pillars,
                'A faixa dos pilares deve permanecer transparente sobre a superfície da página.'
            );
            self::assertSame(
                $palette['surface'],
                $palette['page'],
                'A superfície da página deve ser a mesma --surface pintada na faixa da vitrine.'
            );

            self::assertNotSame(
                $hero,
                $showcase,
                'A alternância entre faixas azuis e claras é o que dá ritmo vertical à página.'
            );

            // O último passo do "Como funciona" fecha o fluxo em menta: a
            // regra vive só no SCSS, então sem esta leitura do estilo
            // computado uma troca de token deixaria o círculo azul como os
            // demais sem quebrar nenhum teste de markup.
            self::assertSame(
                $palette['successContainer'],
                $palette['finalStep'],
                'O passo de conclusão deve pintar o fundo com --success-container (menta).'
            );
            self::assertNotSame(
                $palette['plainStep'],
                $palette['finalStep'],
                'O passo de conclusão deve se distinguir visualmente dos passos anteriores.'
            );
        });
    }

    /**
     * Lê o CSS computado de um único elemento.
     */
    private function computedStyle(Browser $browser, string $selector, string $property): string
    {
        return $browser->script(sprintf(
            'return getComputedStyle(document.querySelector(%s))[%s];',
            json_encode($selector, JSON_THROW_ON_ERROR),
            json_encode($property, JSON_THROW_ON_ERROR)
        ))[0];
    }

    /**
     * Medidas de layout da Landing Page na largura atual do viewport.
     *
     * `getBoundingClientRect()` (e não a propriedade `height`) para o header:
     * com `box-sizing: border-box` a altura computada exclui o `border-bottom`
     * de 1px e reportaria 75px em vez dos 76px de `--appbar-height`.
     *
     * @return array{scrollWidth: int, innerWidth: int, headerHeight: int, brandDisplay: string, grid3Tracks: array<int, int>, grid4Tracks: array<int, int>, footerJustify: string, footerTextAlign: string}
     */
    private function landingLayout(Browser $browser): array
    {
        /** @var array{scrollWidth: int, innerWidth: int, headerHeight: float, brandDisplay: string, grid3Tracks: array<int, int>, grid4Tracks: array<int, int>, footerJustify: string, footerTextAlign: string} $layout */
        $layout = $browser->script(<<<'JS'
            const trackCount = (selector) => Array.from(document.querySelectorAll(selector))
                .map((element) => getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/).length);
            const computed = (selector, property) => getComputedStyle(document.querySelector(selector))[property];
            const header = document.querySelector('.landing-header');

            return {
                scrollWidth: document.body.scrollWidth,
                innerWidth: window.innerWidth,
                headerHeight: Math.round(header.getBoundingClientRect().height),
                brandDisplay: computed('.landing-brand-name', 'display'),
                grid3Tracks: trackCount('.landing-grid-3'),
                grid4Tracks: trackCount('.landing-grid-4'),
                footerJustify: computed('.landing-footer-inner', 'justifyContent'),
                footerTextAlign: computed('.landing-footer-inner', 'textAlign'),
            };
        JS)[0];

        return [
            'scrollWidth' => (int) $layout['scrollWidth'],
            'innerWidth' => (int) $layout['innerWidth'],
            'headerHeight' => (int) $layout['headerHeight'],
            'brandDisplay' => $layout['brandDisplay'],
            'grid3Tracks' => array_map(intval(...), $layout['grid3Tracks']),
            'grid4Tracks' => array_map(intval(...), $layout['grid4Tracks']),
            'footerJustify' => $layout['footerJustify'],
            'footerTextAlign' => $layout['footerTextAlign'],
        ];
    }
}
