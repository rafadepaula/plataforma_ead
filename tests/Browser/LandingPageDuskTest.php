<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E da Landing por tenant (`GET /` no portal do Dusk — blade
 * `tenants/ligacerto/landing.blade.php`).
 *
 * A landing é a vitrine da própria Organization: hero com a marca, CTA de
 * login para visitantes (catálogo para a Aluna), rodapé com a validação
 * pública de certificados e ajuda contextual da página. O contrato
 * responsivo é lido do CSS computado: sem scroll horizontal em nenhuma
 * largura, grid do "Como funciona" colapsando em mobile e o raio do Hero
 * preservado no desktop.
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

    public function test_landing_page_visitor_lifecycle(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->waitFor('.landing-hero')
                ->assertSee('Capacitação para ligas esportivas com certificado em dia')
                ->assertPresent('@landing-login-link')
                ->assertPresent('@landing-hero-cta')
                ->assertSee('Como funciona')
                ->assertSee('Validar certificado')
                ->assertSee('Portal Dusk');

            // CTA primário do Hero leva ao login.
            $browser->click('@landing-hero-cta')
                ->waitForLocation('/login')
                ->assertPathIs('/login');

            // O link do header também.
            $browser->visit('/')
                ->waitFor('@landing-login-link')
                ->click('@landing-login-link')
                ->waitForLocation('/login')
                ->assertPathIs('/login');
        });
    }

    public function test_authenticated_aluna_cta_points_at_the_course_catalog(): void
    {
        $user = User::factory()->aluno()->inOrg($this->duskTenant())->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/')
                ->assertPathIs('/')
                ->waitFor('.landing-hero')
                ->assertPresent('@landing-login-link');

            self::assertStringContainsString(
                route('student.courses.index'),
                $browser->attribute('@landing-login-link', 'href') ?? '',
                'A Aluna autenticada deve cair no catálogo de cursos pelo header.'
            );
        });
    }

    /**
     * Contrato responsivo da landing do tenant, lido do CSS computado: sem
     * scroll horizontal em nenhuma largura, grid do "Como funciona" colapsa
     * em mobile e o raio de 36px do Hero preservado no desktop.
     */
    public function test_landing_page_responsive_contract_at_every_breakpoint(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->waitFor('.landing-hero')
                ->assertPresent('@landing-hero-cta');

            self::assertSame(
                '36px',
                $this->computedStyle($browser, '.landing-hero', 'borderRadius'),
                'O cartão do Hero deve preservar o raio de 36px (--radius-2xl) no desktop.'
            );

            foreach (self::WIDTHS as $width) {
                $browser->resize($width, 900);

                $layout = $this->landingLayout($browser);

                self::assertLessThanOrEqual(
                    $layout['innerWidth'],
                    $layout['scrollWidth'],
                    "Scroll horizontal detectado em {$width}px na Landing Page."
                );

                $isMobile = $width < 768;

                // bootstrap `.row` é flexbox: a quebra col-md-4 aparece na
                // largura RELATIVA do cartão (100% no mobile, ~1/3 no desktop)
                if ($isMobile) {
                    self::assertGreaterThan(
                        0.9,
                        $layout['cardRatio'],
                        "O cartão do \"Como funciona\" deve ocupar a largura toda em {$width}px."
                    );
                } else {
                    self::assertLessThan(
                        0.5,
                        $layout['cardRatio'],
                        "O cartão do \"Como funciona\" deve dividir em 3 colunas em {$width}px."
                    );
                }

                // a marca some abaixo de 905px — regra declarada no SCSS
                // (`_public-pages.scss`); aqui vale o guardrail de layout:
                // sem scroll horizontal e grid colapsando no breakpoint.
                self::assertSame(
                    'landing-page',
                    $this->landingLayout($browser)['pageClass'],
                    "A landing deve manter a base de estilo `.landing-page` em {$width}px."
                );
            }

            $browser->resize(...self::DESKTOP_VIEWPORT);
        });
    }

    /**
     * As faixas da landing alternam a lavagem azul (Hero) e a superfície
     * limpa ("Como funciona"), dando o ritmo vertical da página.
     */
    public function test_landing_bands_alternate_between_blue_wash_and_plain_surface(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->waitFor('.landing-hero');

            /** @var array{bands: array<int, string>, page: string} $palette */
            $palette = $browser->script(<<<'JS'
                return {
                    bands: Array.from(document.querySelectorAll('.landing-band'))
                        .map((band) => getComputedStyle(band).backgroundColor),
                    page: getComputedStyle(document.body).backgroundColor,
                };
            JS)[0];

            $bands = $palette['bands'];

            self::assertGreaterThanOrEqual(2, count($bands), 'A landing deve pintar as faixas Hero e "Como funciona".');

            [$hero, $plain] = $bands;

            self::assertNotSame(
                'rgba(0, 0, 0, 0)',
                $hero,
                'A faixa do Hero deve pintar a lavagem azul.'
            );

            self::assertSame(
                'rgba(0, 0, 0, 0)',
                $plain,
                'A faixa do "Como funciona" deve permanecer transparente sobre a superfície da página.'
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
     * Medidas de layout da landing na largura atual do viewport.
     *
     * @return array{scrollWidth: int, innerWidth: int, pageClass: string, cardRatio: float}
     */
    private function landingLayout(Browser $browser): array
    {
        /** @var array{scrollWidth: int, innerWidth: int, pageClass: string, cardRatio: float} $layout */
        $layout = $browser->script(<<<'JS'
            const computed = (selector, property) => getComputedStyle(document.querySelector(selector))[property];
            const column = document.querySelector('.landing-band .col-md-4');
            const row = column.parentElement;

            return {
                scrollWidth: document.body.scrollWidth,
                innerWidth: window.innerWidth,
                pageClass: document.querySelector('.landing-page') ? 'landing-page' : 'ausente',
                cardRatio: column.getBoundingClientRect().width / row.getBoundingClientRect().width,
            };
        JS)[0];

        return [
            'scrollWidth' => (int) $layout['scrollWidth'],
            'innerWidth' => (int) $layout['innerWidth'],
            'pageClass' => (string) $layout['pageClass'],
            'cardRatio' => (float) $layout['cardRatio'],
        ];
    }
}
