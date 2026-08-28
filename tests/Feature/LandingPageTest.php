<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\User;
use Tests\TestCase;

/**
 * Public Landing Page and Component Showcase (`GET /`, `landing.show`).
 */
class LandingPageTest extends TestCase
{
    public function test_landing_page_is_reachable_without_authentication(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_landing_page_is_reachable_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('landing.show'));

        $response->assertOk();
    }

    public function test_landing_page_renders_all_seven_bands_and_key_copy(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        // 1. Header Band
        $response->assertSee(config('app.name', 'Plataforma EAD'));
        $response->assertSee('dusk="landing-login-link"', false);
        $response->assertSee('Entrar');

        // 2. Hero Band
        $response->assertSee('dusk="landing-headline"', false);
        $response->assertSee('Capacitação técnica continuada, do jeito certo');
        $response->assertSee('Cursos, provas interativas e certificados oficiais em uma única plataforma');
        $response->assertSee('dusk="landing-cta-login"', false);
        $response->assertSee('Acessar plataforma');

        // 3. Capabilities Band (3 Pillars)
        $response->assertSee('Gestão Multitenant');
        $response->assertSee('Ambientes isolados por Organização');
        $response->assertSee('Experiência de Aprendizado');
        $response->assertSee('Aulas em vídeo, PDFs para download');
        $response->assertSee('Certificação Confiável');
        $response->assertSee('Emissão automatizada de certificados com hash SHA-256');

        // 4. Process Band (4 Steps)
        $response->assertSee('Do convite ao certificado');
        $response->assertSee('Como funciona');
        $response->assertSee('A Organização publica');
        $response->assertSee('Você recebe o convite');
        $response->assertSee('Você estuda e é avaliado');
        $response->assertSee('O Certificado sai na hora');

        // 5. Showcase Band (Real Components)
        $response->assertSee('Por dentro da plataforma');
        $response->assertSee('As telas que você vai usar');
        $response->assertSee('Sem montagem: são os mesmos componentes que aparecem depois do login.');
        // Course Showcase Card
        $response->assertSee('Segurança do trabalho — NR 35');
        $response->assertSee('Em andamento');
        $response->assertSee('62%');
        $response->assertSee('Continue de onde parou · Aula 12 de 18');
        // Certificate Showcase Card
        $response->assertSee('Certificado emitido');
        $response->assertSee('nº 9f2b7c41');
        $response->assertSee('Válido');
        $response->assertSee('Validação pública');
        $response->assertSee('Baixar certificado');
        // Forum Showcase Card
        $response->assertSee('Joana Ribeiro');
        $response->assertSee('Como registrar o ponto de ancoragem na prática?');
        $response->assertSee('7 respostas');

        // 6. Contact Band
        $response->assertSee('id="contato"', false);
        $response->assertSee('Deseja utilizar esta plataforma em sua organização?');
        $response->assertSee('Fale conosco');

        // 7. Footer Band
        $response->assertSee('Validar certificado');
        $response->assertSee('Termos de uso');
        $response->assertSee('Privacidade');
        $response->assertSee('Suporte');
    }

    public function test_landing_page_login_ctas_link_to_login_route(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();

        $loginUrl = route('login');
        $content = $response->getContent();

        $this->assertStringContainsString('href="'.$loginUrl.'"', $content);
        $this->assertMatchesRegularExpression('/href="[^"]*login[^"]*"[^>]*dusk="landing-login-link"|dusk="landing-login-link"[^>]*href="[^"]*login[^"]*"/', $content);
        $this->assertMatchesRegularExpression('/href="[^"]*login[^"]*"[^>]*dusk="landing-cta-login"|dusk="landing-cta-login"[^>]*href="[^"]*login[^"]*"/', $content);
    }

    public function test_landing_page_renders_help_button_with_placeholder_when_no_article_exists(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('help-button-landing', false);
        $response->assertSee('help-modal-landing', false);
        $response->assertSee('help-placeholder-content-landing', false);
        $response->assertSee('Estamos preparando o conteúdo de ajuda desta tela.');
    }

    public function test_landing_page_renders_help_button_with_resolved_article_content(): void
    {
        $article = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
            'target_page_key' => 'landing',
            'title' => 'Ajuda da Página Inicial',
            'content' => 'Conheça nossa plataforma de capacitação técnica.',
        ]));

        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('help-button-landing', false);
        $response->assertSee('help-modal-landing', false);
        $response->assertSee('help-article-content-landing', false);
        $response->assertSee('Ajuda da Página Inicial');
        $response->assertSee('Conheça nossa plataforma de capacitação técnica.');
    }
}
