<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * Public tenant Landing (`GET /`, `landing.show`) — key copy and the
 * contextual help surface of the per-Organization landing blade.
 */
class LandingPageTest extends TestCase
{
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create([
            'name' => 'Liga Paulista de Kaioke',
            'landing_view' => 'ligacerto',
        ]);
        $this->onHost($this->org->host);
    }

    public function test_landing_page_is_reachable_without_authentication(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_landing_page_is_reachable_by_authenticated_user(): void
    {
        $user = User::factory()->inOrg($this->org)->create();

        $this->actingAs($user)->get(route('landing.show'))->assertOk();
    }

    public function test_landing_page_renders_the_tenant_brand_and_key_copy(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        // Header band carries the Organization brand (not the platform name)
        $response->assertSee('Liga Paulista de Kaioke');
        $response->assertSee('dusk="landing-login-link"', false);

        // Hero band
        $response->assertSee('Capacitação para ligas esportivas com certificado em dia');

        // "Como funciona" band
        $response->assertSee('Como funciona');

        // Footer band
        $response->assertSee('Validar certificado');
    }

    public function test_landing_page_login_ctas_link_to_login_route(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        $loginUrl = route('login');
        $content = $response->getContent();

        $this->assertStringContainsString('href="'.$loginUrl.'"', $content);
        $this->assertMatchesRegularExpression('/href="[^"]*login[^"]*"[^>]*dusk="landing-login-link"|dusk="landing-login-link"[^>]*href="[^"]*login[^"]*"/', $content);
        $this->assertMatchesRegularExpression('/href="[^"]*login[^"]*"[^>]*dusk="landing-hero-cta"|dusk="landing-hero-cta"[^>]*href="[^"]*login[^"]*"/', $content);
    }

    public function test_landing_page_does_not_render_a_contextual_help_button(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertDontSee('help-button-landing', false);
        $response->assertDontSee('help-modal-landing', false);
    }
}
