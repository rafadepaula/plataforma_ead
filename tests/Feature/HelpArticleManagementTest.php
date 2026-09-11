<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\Organization;
use App\Services\HelpArticleResolverService;
use Tests\TestCase;

class HelpArticleManagementTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_management_routes(): void
    {
        $response = $this->get(route('org.help.artigos.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_aluno_and_professor_cannot_access_help_article_management(): void
    {
        $org = Organization::factory()->create();

        foreach (['aluno', 'professor'] as $role) {
            $this->actingAsOrgUser($org, $role);

            $this->get(route('org.help.artigos.index'))->assertForbidden();
            $this->get(route('org.help.artigos.create'))->assertForbidden();
            $this->post(route('org.help.artigos.store'), [])->assertForbidden();
            $this->post(route('org.help.preview'), [])->assertForbidden();
        }
    }

    public function test_admin_can_view_index_and_create_global_article(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('org.help.artigos.index'));

        $response->assertOk();
        $response->assertSee('Artigos de Ajuda');
        $response->assertSee('dusk="new-help-article"', false);

        $createResponse = $this->get(route('org.help.artigos.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('dusk="org-help-article-form"', false);

        $storeResponse = $this->post(route('org.help.artigos.store'), [
            'title' => 'Painel Administrativo',
            'slug' => 'painel-administrativo',
            'category' => 'Administração',
            'target_page_key' => 'admin.dashboard',
            'content' => '## Guia do Painel Administrativo',
            'org_id' => null,
        ]);

        $storeResponse->assertRedirect(route('org.help.artigos.index'));
        $storeResponse->assertSessionHas('success');

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'painel-administrativo',
            'org_id' => null,
            'target_page_key' => 'admin.dashboard',
        ]);
    }

    public function test_gestor_can_create_article_for_own_organization(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);

        $response = $this->post(route('org.help.artigos.store'), [
            'title' => 'Regras da Organização',
            'slug' => 'regras-da-organizacao',
            'category' => 'Geral',
            'target_page_key' => 'courses.index',
            'content' => 'Conteúdo específico da nossa escola.',
        ]);

        $response->assertRedirect(route('org.help.artigos.index'));

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'regras-da-organizacao',
            'org_id' => $org->id,
            'target_page_key' => 'courses.index',
        ]);
    }

    public function test_gestor_index_does_not_list_articles_from_other_organizations(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $this->actingAsOrgUser($org1);

        HelpArticle::withoutEvents(function () use ($org1, $org2): void {
            HelpArticle::factory()->global()->create([
                'title' => 'Artigo Global de Ajuda',
                'slug' => 'artigo-global-ajuda',
            ]);

            HelpArticle::factory()->forOrg($org1)->create([
                'title' => 'Artigo Exclusivo Org 1',
                'slug' => 'artigo-exclusivo-org-1',
            ]);

            HelpArticle::factory()->forOrg($org2)->create([
                'title' => 'Artigo Secreto Org 2',
                'slug' => 'artigo-secreto-org-2',
            ]);
        });

        $response = $this->get(route('org.help.artigos.index'));

        $response->assertOk();
        $response->assertSee('Artigo Global de Ajuda');
        $response->assertSee('Artigo Exclusivo Org 1');
        $response->assertDontSee('Artigo Secreto Org 2');
    }

    public function test_gestor_cannot_edit_update_or_delete_articles_from_other_organizations(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $this->actingAsOrgUser($org1);

        $foreignArticle = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($org2)->create([
            'title' => 'Artigo Org 2',
            'slug' => 'artigo-org-2',
        ]));

        $this->get(route('org.help.artigos.edit', $foreignArticle->id))
            ->assertNotFound();

        $this->put(route('org.help.artigos.update', $foreignArticle->id), [
            'title' => 'Tentativa de Hack',
            'slug' => 'artigo-org-2',
            'category' => 'Geral',
            'content' => 'Invasão',
        ])
            ->assertNotFound();

        $this->delete(route('org.help.artigos.destroy', $foreignArticle->id))
            ->assertNotFound();
    }

    public function test_gestor_cannot_edit_update_or_delete_global_articles(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);

        $globalArticle = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
            'title' => 'Artigo Global',
            'slug' => 'artigo-global-lock',
        ]));

        $this->get(route('org.help.artigos.edit', $globalArticle->id))
            ->assertNotFound();

        $this->put(route('org.help.artigos.update', $globalArticle->id), [
            'title' => 'Tentativa de Editar Global',
            'slug' => 'artigo-global-lock',
            'category' => 'Geral',
            'content' => 'Conteúdo adulterado',
        ])
            ->assertNotFound();

        $this->delete(route('org.help.artigos.destroy', $globalArticle->id))
            ->assertNotFound();
    }

    public function test_gestor_can_update_and_delete_own_article(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);

        $article = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($org)->create([
            'title' => 'Artigo Próprio',
            'slug' => 'artigo-proprio',
            'category' => 'Cursos',
            'content' => 'Original',
        ]));

        $editResponse = $this->get(route('org.help.artigos.edit', $article->id));
        $editResponse->assertOk();
        $editResponse->assertSee('dusk="save-help-article"', false);

        $updateResponse = $this->put(route('org.help.artigos.update', $article->id), [
            'title' => 'Artigo Próprio Atualizado',
            'slug' => 'artigo-proprio-atualizado',
            'category' => 'Cursos',
            'content' => 'Atualizado',
        ]);

        $updateResponse->assertRedirect(route('org.help.artigos.index'));

        $this->assertDatabaseHas('help_articles', [
            'id' => $article->id,
            'title' => 'Artigo Próprio Atualizado',
            'slug' => 'artigo-proprio-atualizado',
        ]);

        $deleteResponse = $this->delete(route('org.help.artigos.destroy', $article->id));
        $deleteResponse->assertRedirect(route('org.help.artigos.index'));

        $this->assertDatabaseMissing('help_articles', [
            'id' => $article->id,
        ]);
    }

    public function test_admin_can_update_and_delete_any_article(): void
    {
        $this->actingAsAdmin();

        $org = Organization::factory()->create();
        $article = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($org)->create([
            'title' => 'Artigo de Org para Admin',
            'slug' => 'artigo-org-admin',
        ]));

        $updateResponse = $this->put(route('org.help.artigos.update', $article->id), [
            'title' => 'Modificado pelo Admin',
            'slug' => 'artigo-org-admin',
            'category' => 'Ajuste',
            'content' => 'Conteúdo ajustado pelo Admin',
            'org_id' => $org->id,
        ]);

        $updateResponse->assertRedirect(route('org.help.artigos.index'));

        $this->assertDatabaseHas('help_articles', [
            'id' => $article->id,
            'title' => 'Modificado pelo Admin',
        ]);

        $deleteResponse = $this->delete(route('org.help.artigos.destroy', $article->id));
        $deleteResponse->assertRedirect(route('org.help.artigos.index'));

        $this->assertDatabaseMissing('help_articles', [
            'id' => $article->id,
        ]);
    }

    public function test_slug_uniqueness_is_validated(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);

        HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($org)->create([
            'slug' => 'slug-em-uso',
        ]));

        $response = $this->post(route('org.help.artigos.store'), [
            'title' => 'Outro Artigo',
            'slug' => 'slug-em-uso',
            'category' => 'Geral',
            'content' => 'Conteúdo',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_preview_endpoint_renders_sanitized_markdown(): void
    {
        $this->actingAsAdmin();

        $markdownContent = "# Título Principal\n\nEste é um **texto em negrito**.\n\n<script>alert('xss')</script>";

        $response = $this->postJson(route('org.help.preview'), [
            'content' => $markdownContent,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['html']);

        $html = $response->json('html');
        $this->assertStringContainsString('<h1>Título Principal</h1>', $html);
        $this->assertStringContainsString('<strong>texto em negrito</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_org_article_overrides_global_article_in_resolver_when_created(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);

        HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
            'target_page_key' => 'courses.index',
            'title' => 'Global Courses Help',
            'slug' => 'global-courses-help',
        ]));

        $resolver = app(HelpArticleResolverService::class);
        $resolvedBefore = $resolver->resolve('courses.index', $org->id);
        $this->assertSame('Global Courses Help', $resolvedBefore->title);

        $this->actingAsOrgUser($org);

        $this->post(route('org.help.artigos.store'), [
            'title' => 'Org Custom Courses Help',
            'slug' => 'org-custom-courses-help',
            'category' => 'Cursos',
            'target_page_key' => 'courses.index',
            'content' => 'Regras customizadas para nossa organização.',
        ]);

        $resolvedAfter = $resolver->resolve('courses.index', $org->id);
        $this->assertSame('Org Custom Courses Help', $resolvedAfter->title);
        $this->assertSame($org->id, $resolvedAfter->org_id);
    }
}
