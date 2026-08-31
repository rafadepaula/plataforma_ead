<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Tests\TestCase;

/**
 * Public Landing Page render contract (`GET /`, `landing.show`,
 * `LandingPageController`).
 *
 * The controller is a thin static-page renderer: every assertion here is
 * about what the Blade view paints (band structure, verbatim copy, design
 * system variants, footer links) and where the CTAs point for each role.
 * Contextual help coverage lives in `LandingPageTest`.
 */
class LandingPageControllerTest extends TestCase
{
    public function test_landing_page_returns_ok_for_guest_and_every_authenticated_role(): void
    {
        $this->get(route('landing.show'))->assertOk();

        $student = User::factory()->aluno()->create();
        $this->actingAs($student)->get(route('landing.show'))->assertOk();

        $manager = User::factory()->gestor()->create();
        $this->actingAs($manager)->get(route('landing.show'))->assertOk();

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin)->get(route('landing.show'))->assertOk();
    }

    public function test_landing_page_renders_the_seven_bands_in_order(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertViewIs('landing.show');

        $content = $response->getContent();

        // 1. Public header.
        $this->assertStringContainsString('landing-header', $content);
        $this->assertStringContainsString(config('app.name', 'Plataforma EAD'), $content);

        // 2. Hero band.
        $this->assertStringContainsString('landing-hero-band', $content);

        // 3. Capabilities / 3 pillars.
        $this->assertStringContainsString('Gestão Multitenant', $content);
        $this->assertStringContainsString('Experiência de Aprendizado', $content);
        $this->assertStringContainsString('Certificação Confiável', $content);

        // 4. "Como funciona" with the numbered steps.
        $this->assertStringContainsString('Como funciona', $content);

        // 5. Showcase of real design system components.
        $this->assertStringContainsString('As telas que você vai usar', $content);

        // 6. Institutional contact anchor.
        $this->assertMatchesRegularExpression('/<section id="contato"/', $content);

        // 7. Public footer.
        $this->assertStringContainsString('landing-footer-inner', $content);

        // The seven bands appear in document order.
        $bands = ['landing-header', 'landing-hero-band', 'Gestão Multitenant', 'Como funciona', 'As telas que você vai usar', 'id="contato"', 'landing-footer-inner'];
        $positions = array_map(fn (string $needle): int => (int) mb_strpos((string) $content, $needle), $bands);

        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_landing_page_renders_verbatim_hero_copy_and_primary_cta(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('Educação a Distância');
        $response->assertSee('Capacitação técnica continuada, do jeito certo');
        $response->assertSee('Cursos, provas interativas e certificados oficiais em uma única plataforma,', false);
        $response->assertSee('pensada para organizações que levam a formação de suas equipes a sério.', false);
        $response->assertSee('Acessar plataforma');
        $response->assertSee('dusk="landing-headline"', false);
        $response->assertSee('dusk="landing-cta-login"', false);
        $response->assertSee('dusk="landing-login-link"', false);
    }

    public function test_landing_page_renders_the_showcase_cards_copy(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        // Course card.
        $response->assertSee('Segurança do trabalho — NR 35');
        $response->assertSee('Em andamento');
        $response->assertSee('62%');
        // Certificate card.
        $response->assertSee('nº 9f2b7c41');
        $response->assertSee('Válido');
        $response->assertSee('Validação pública');
        $response->assertSee('Baixar certificado');
        // Forum card.
        $response->assertSee('7 respostas');
    }

    public function test_landing_page_contact_band_renders_a_tonal_button(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        $content = $response->getContent();
        $contactAnchor = $this->elementWithDusk($content, 'contact-button');

        $this->assertStringContainsString('btn-tonal', $contactAnchor);
        $this->assertStringContainsString('href="mailto:contato@plataformaead.com.br"', $contactAnchor);
        $response->assertSee('Fale conosco');
    }

    public function test_landing_page_certificate_card_uses_a_download_icon(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('lucide-download', false);
        // Lucide `download`: tray plus the arrow pointing into it.
        $response->assertSee('points="7 10 12 15 17 10"', false);

        $content = $response->getContent();
        $this->assertStringNotContainsString('lucide-upload', $content);
    }

    public function test_landing_page_marks_the_final_process_step_as_concluded(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        $content = $response->getContent();

        $response->assertSee('class="landing-step landing-step--success"', false);
        $this->assertSame(1, substr_count($content, 'landing-step--success'));
    }

    public function test_landing_page_brand_mark_initials_follow_the_configured_app_name(): void
    {
        config(['app.name' => 'Instituto Federal de Educação']);

        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('<span class="brand-mark" aria-hidden="true">IF</span>', false);
    }

    public function test_landing_page_footer_renders_dynamic_copyright_and_public_validation_entry_point(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('&copy; '.date('Y'), false);
        $response->assertSee('Todos os direitos reservados.', false);

        // O rodapé aponta para o formulário de hash, nunca para um hash fixo:
        // um link com hash embutido resolveria sempre em 404, deixando a
        // entrada pública de validação inalcançável.
        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\\s[^>]*href="'.preg_quote(route('certificates.verify'), '/').'"[^>]*>\\s*Validar certificado\\s*<\\/a>/',
            $content,
        );

        $this->assertSame(
            '/validar-certificado',
            parse_url(route('certificates.verify'), PHP_URL_PATH),
        );
    }

    public function test_public_certificate_validation_entry_point_renders_the_hash_form(): void
    {
        $response = $this->get(route('certificates.verify'));

        $response->assertOk();
        $response->assertSee('certificate-lookup-form', false);
        $response->assertSee('name="hash"', false);
    }

    public function test_landing_page_ctas_point_at_the_role_dashboard(): void
    {
        $guest = $this->get(route('landing.show'));
        $guest->assertOk();
        $this->assertStringContainsString(
            'href="'.route('login').'"',
            $this->elementWithDusk($guest->getContent(), 'landing-cta-login'),
        );

        $student = User::factory()->aluno()->create();
        $studentResponse = $this->actingAs($student)->get(route('landing.show'));
        $studentResponse->assertOk();
        $this->assertStringContainsString(
            'href="'.route('student.courses.index').'"',
            $this->elementWithDusk($studentResponse->getContent(), 'landing-cta-login'),
        );

        $manager = User::factory()->gestor()->create();
        $managerResponse = $this->actingAs($manager)->get(route('landing.show'));
        $managerResponse->assertOk();
        $this->assertStringContainsString(
            'href="'.route('admin.dashboard').'"',
            $this->elementWithDusk($managerResponse->getContent(), 'landing-cta-login'),
        );
        $this->assertStringContainsString(
            'href="'.route('admin.dashboard').'"',
            $this->elementWithDusk($managerResponse->getContent(), 'landing-login-link'),
        );
    }

    public function test_landing_page_cta_for_admin_points_at_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('landing.show'));

        $response->assertOk();
        $this->assertStringContainsString(
            'href="'.route('admin.dashboard').'"',
            $this->elementWithDusk($response->getContent(), 'landing-cta-login'),
        );
    }

    /**
     * Return the first HTML tag carrying the given `dusk` attribute.
     *
     * @param  string  $content  Rendered response body.
     * @param  string  $dusk  Value of the `dusk` attribute.
     * @return string The matched opening tag.
     */
    private function elementWithDusk(string $content, string $dusk): string
    {
        $pattern = '/<a\b[^>]*\bdusk="'.preg_quote($dusk, '/').'"[^>]*>/s';

        $this->assertSame(1, preg_match($pattern, $content, $matches));

        return $matches[0];
    }
}
