<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * the public, unauthenticated Landing Page
 * (`GET /`, `landing.show`).
 */
class LandingPageTest extends TestCase
{
    public function test_landing_page_is_reachable_without_authentication(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_landing_page_shows_expected_marketing_content(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('Capacitação técnica continuada, do jeito certo');
        $response->assertSee('Entrar');
    }

    public function test_landing_page_renders_the_help_button(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('help-button-landing', false);
    }
}
