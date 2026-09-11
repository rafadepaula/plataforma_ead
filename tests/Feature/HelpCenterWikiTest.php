<?php

namespace Tests\Feature;

use App\Enums\Help\HelpAudienceEnum;
use App\Models\HelpArticle;
use App\Models\Organization;
use Tests\TestCase;

class HelpCenterWikiTest extends TestCase
{
    public function test_public_help_center_index_is_accessible_without_authentication(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Centro de Ajuda');
        $response->assertSee('dusk="help-center-index"', false);
        $response->assertSee('dusk="help-center-search"', false);
    }

    public function test_public_help_center_lists_global_articles_grouped_by_category(): void
    {
        HelpArticle::withoutEvents(function (): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Como navegar na plataforma',
                'slug' => 'como-navegar-na-plataforma',
                'category' => 'Geral',
                'content' => 'Primeiros passos para usar o sistema.',
            ]);

            HelpArticle::factory()->global()->create([
                'title' => 'Emitindo certificados',
                'slug' => 'emitindo-certificados',
                'category' => 'Certificados',
                'content' => 'Informações sobre emissão de certificados.',
            ]);
        });

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Geral');
        $response->assertSee('Como navegar na plataforma');
        $response->assertSee('Certificados');
        $response->assertSee('Emitindo certificados');
        $response->assertSee(route('help.show', 'como-navegar-na-plataforma'));
    }

    public function test_search_filters_articles_by_title_or_content(): void
    {
        HelpArticle::withoutEvents(function (): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Guia do Aluno',
                'slug' => 'guia-do-aluno',
                'category' => 'Aluno',
                'content' => 'Conteúdo para estudantes.',
            ]);

            HelpArticle::factory()->global()->create([
                'title' => 'Gestão de Usuários',
                'slug' => 'gestao-de-usuarios',
                'category' => 'Gestão',
                'content' => 'Administração de contas.',
            ]);
        });

        $response = $this->get(route('help.index', ['q' => 'Aluno']));

        $response->assertOk();
        $response->assertSee('Guia do Aluno');
        $response->assertDontSee('Gestão de Usuários');
    }

    public function test_search_with_no_matches_displays_empty_state(): void
    {
        $response = $this->get(route('help.index', ['q' => 'termo_que_nao_existe_xyz']));

        $response->assertOk();
        $response->assertSee('Nenhum artigo encontrado');
        $response->assertSee('Ver todos os artigos');
    }

    public function test_public_article_detail_page_renders_markdown_content_and_metadata(): void
    {
        HelpArticle::withoutEvents(function (): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Artigo Completo com Markdown',
                'slug' => 'artigo-completo-com-markdown',
                'category' => 'Documentação',
                'content' => "## Subtítulo do Artigo\n\nEste é um **parágrafo explicativo** com [Link](https://example.com).\n\n- Tópico 1\n- Tópico 2",
            ]);
        });

        $response = $this->get(route('help.show', 'artigo-completo-com-markdown'));

        $response->assertOk();
        $response->assertSee('dusk="help-article-page"', false);
        $response->assertSee('Artigo Completo com Markdown');
        $response->assertSee('Documentação');
        $response->assertSee('<h2>Subtítulo do Artigo</h2>', false);
        $response->assertSee('<strong>parágrafo explicativo</strong>', false);
        $response->assertSee('<li>Tópico 1</li>', false);
        $response->assertSee('class="ds-prose"', false);
        $response->assertSee('Voltar ao Centro de Ajuda');
    }

    public function test_guest_cannot_see_or_access_organization_specific_articles(): void
    {
        $org = Organization::factory()->create();

        HelpArticle::factory()->forOrg($org)->create([
            'title' => 'Artigo Exclusivo da Organização',
            'slug' => 'artigo-exclusivo-da-organizacao',
            'category' => 'Interno',
            'content' => 'Segredo interno da organização.',
        ]);

        // Visitante deslogado no index não vê o artigo
        $indexResponse = $this->get(route('help.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('Artigo Exclusivo da Organização');

        // Visitante deslogado acessando slug direto recebe 404 (nunca 403)
        $showResponse = $this->get(route('help.show', 'artigo-exclusivo-da-organizacao'));
        $showResponse->assertNotFound();
    }

    public function test_wiki_is_org_independent_and_shows_only_global_articles(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        HelpArticle::withoutEvents(function () use ($org, $otherOrg): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Artigo Global Visível a Todos',
                'slug' => 'artigo-global-visivel',
            ]);

            HelpArticle::factory()->forOrg($org)->create([
                'title' => 'Artigo da Minha Organização',
                'slug' => 'artigo-minha-org',
            ]);

            HelpArticle::factory()->forOrg($otherOrg)->create([
                'title' => 'Artigo de Outra Organização',
                'slug' => 'artigo-outra-org',
            ]);
        });

        // Mesmo autenticado como gestor da org dona de um artigo, a wiki
        // pública é independente de org: só conteúdo global aparece.
        $this->actingAsOrgUser($org, 'gestor');

        $indexResponse = $this->get(route('help.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Artigo Global Visível a Todos');
        $indexResponse->assertDontSee('Artigo da Minha Organização');
        $indexResponse->assertDontSee('Artigo de Outra Organização');

        // Artigo da própria org não é conteúdo de wiki (404, nunca 403)
        $showOwnResponse = $this->get(route('help.show', 'artigo-minha-org'));
        $showOwnResponse->assertNotFound();

        // Artigo de outra org também dá 404
        $showOtherResponse = $this->get(route('help.show', 'artigo-outra-org'));
        $showOtherResponse->assertNotFound();
    }

    public function test_admin_sees_only_global_articles_on_wiki_even_when_impersonating(): void
    {
        $org = Organization::factory()->create();

        HelpArticle::withoutEvents(function () use ($org): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Artigo Global',
                'slug' => 'artigo-global-admin',
            ]);

            HelpArticle::factory()->forOrg($org)->create([
                'title' => 'Artigo Org Impersonada',
                'slug' => 'artigo-org-impersonada',
            ]);
        });

        $this->actingAsAdmin();

        $response = $this->get(route('help.index'));
        $response->assertOk();
        $response->assertSee('Artigo Global');
        $response->assertDontSee('Artigo Org Impersonada');

        // Nem a impersonação traz artigos de org para a wiki pública
        $this->withSession(['active_org_id' => $org->id]);
        $responseWithImpersonate = $this->get(route('help.index'));
        $responseWithImpersonate->assertSee('Artigo Global');
        $responseWithImpersonate->assertDontSee('Artigo Org Impersonada');
    }

    public function test_non_existent_slug_returns_404(): void
    {
        $response = $this->get(route('help.show', 'slug-inexistente-12345'));

        $response->assertNotFound();
    }

    public function test_wiki_hides_staff_audience_articles_from_guests(): void
    {
        HelpArticle::withoutEvents(function (): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Como usar a plataforma',
                'slug' => 'como-usar-a-plataforma',
            ]);

            HelpArticle::factory()->global()->forAudience(HelpAudienceEnum::GESTOR)->create([
                'title' => 'Gerenciando matrículas',
                'slug' => 'gerenciando-matriculas',
            ]);

            HelpArticle::factory()->global()->forAudience(HelpAudienceEnum::ADMIN)->create([
                'title' => 'Administrando organizações',
                'slug' => 'administrando-organizacoes',
            ]);
        });

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Como usar a plataforma');
        $response->assertDontSee('Gerenciando matrículas');
        $response->assertDontSee('Administrando organizações');

        $this->get(route('help.show', 'gerenciando-matriculas'))->assertNotFound();
        $this->get(route('help.show', 'administrando-organizacoes'))->assertNotFound();
    }

    public function test_aluno_sees_only_public_audience_articles(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->seedAudienceFixtures();

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Ajuda do Aluno');
        $response->assertDontSee('Ajuda do Professor');
        $response->assertDontSee('Ajuda do Gestor');
        $response->assertDontSee('Ajuda do Admin');
    }

    public function test_professor_sees_public_and_professor_audience_articles(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'professor');

        $this->seedAudienceFixtures();

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Ajuda do Aluno');
        $response->assertSee('Ajuda do Professor');
        $response->assertDontSee('Ajuda do Gestor');
        $response->assertDontSee('Ajuda do Admin');
    }

    public function test_gestor_sees_public_and_gestor_but_not_admin_articles(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $this->seedAudienceFixtures();

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Ajuda do Aluno');
        $response->assertSee('Ajuda do Gestor');
        $response->assertDontSee('Ajuda do Admin');

        $this->get(route('help.show', 'ajuda-do-admin'))->assertNotFound();
        $this->get(route('help.show', 'ajuda-do-gestor'))->assertOk();
    }

    public function test_admin_sees_all_audience_articles(): void
    {
        $this->actingAsAdmin();

        $this->seedAudienceFixtures();

        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Ajuda do Aluno');
        $response->assertSee('Ajuda do Professor');
        $response->assertSee('Ajuda do Gestor');
        $response->assertSee('Ajuda do Admin');
    }

    private function seedAudienceFixtures(): void
    {
        HelpArticle::withoutEvents(function (): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Ajuda do Aluno',
                'slug' => 'ajuda-do-aluno',
            ]);

            HelpArticle::factory()->global()->forAudience(HelpAudienceEnum::PROFESSOR)->create([
                'title' => 'Ajuda do Professor',
                'slug' => 'ajuda-do-professor',
            ]);

            HelpArticle::factory()->global()->forAudience(HelpAudienceEnum::GESTOR)->create([
                'title' => 'Ajuda do Gestor',
                'slug' => 'ajuda-do-gestor',
            ]);

            HelpArticle::factory()->global()->forAudience(HelpAudienceEnum::ADMIN)->create([
                'title' => 'Ajuda do Admin',
                'slug' => 'ajuda-do-admin',
            ]);
        });
    }
}
