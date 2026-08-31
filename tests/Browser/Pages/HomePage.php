<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * Page object da Landing Page pública (`/`).
 *
 * Os atalhos apontam para os seletores `dusk=` congelados do snapshot
 * (`landing-headline`, `landing-cta-login`, `landing-login-link`) e para a
 * âncora `#contato`, de modo que novos testes de browser parem de repetir os
 * seletores literais.
 *
 * Os valores usam a forma CSS `[dusk="..."]` — e não o atalho `@landing-headline`
 * — porque `ElementResolver::format()` substitui os atalhos de página uma única
 * vez: `@headline` → `@landing-headline` deixaria um seletor inválido no
 * WebDriver em vez de resolver para o elemento.
 */
class HomePage extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return '/';
    }

    /**
     * Assert that the browser is on the page.
     */
    public function assert(Browser $browser): void
    {
        //
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array<string, string>
     */
    public function elements(): array
    {
        return [
            '@headline' => '[dusk="landing-headline"]',
            '@ctaLogin' => '[dusk="landing-cta-login"]',
            '@loginLink' => '[dusk="landing-login-link"]',
            '@contact' => '#contato',
        ];
    }
}
