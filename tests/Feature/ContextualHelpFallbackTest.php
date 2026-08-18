<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\Organization;
use App\Services\HelpArticleResolverService;
use Tests\TestCase;

/**
 * `HelpArticleResolverService::resolve()`'s
 * fallback contract: an org-specific `HelpArticle` wins over a global one
 * for the same `target_page_key`, a global article is served when no
 * org-specific one exists, and resolution is null-safe (no exception, no
 * 500) when neither exists — including for anonymous/public contexts
 * where `$orgId` is `null`.
 */
class ContextualHelpFallbackTest extends TestCase
{
    public function test_org_specific_article_wins_over_global_for_same_target_page_key(): void
    {
        $org = Organization::factory()->create();

        HelpArticle::factory()->global()->create([
            'target_page_key' => 'courses.index',
            'title' => 'Artigo Global',
        ]);

        $orgSpecific = HelpArticle::factory()->forOrg($org)->create([
            'target_page_key' => 'courses.index',
            'title' => 'Artigo da Organização',
        ]);

        $resolved = app(HelpArticleResolverService::class)->resolve('courses.index', $org->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($orgSpecific));
        $this->assertSame('Artigo da Organização', $resolved->title);
    }

    public function test_global_article_is_served_when_no_org_specific_one_exists(): void
    {
        $org = Organization::factory()->create();

        $global = HelpArticle::factory()->global()->create([
            'target_page_key' => 'courses.index',
            'title' => 'Artigo Global',
        ]);

        $resolved = app(HelpArticleResolverService::class)->resolve('courses.index', $org->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($global));
    }

    public function test_returns_null_when_neither_org_specific_nor_global_article_exists(): void
    {
        $org = Organization::factory()->create();

        $resolved = app(HelpArticleResolverService::class)->resolve('nonexistent.page', $org->id);

        $this->assertNull($resolved);
    }

    public function test_resolves_only_the_global_article_for_an_anonymous_null_org_context(): void
    {
        $otherOrg = Organization::factory()->create();

        HelpArticle::factory()->forOrg($otherOrg)->create([
            'target_page_key' => 'landing',
            'title' => 'Artigo de Outra Organização',
        ]);

        $global = HelpArticle::factory()->global()->create([
            'target_page_key' => 'landing',
            'title' => 'Artigo Global da Landing Page',
        ]);

        $resolved = app(HelpArticleResolverService::class)->resolve('landing', null);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($global));
    }

    public function test_does_not_throw_and_returns_null_for_anonymous_context_without_a_global_article(): void
    {
        $resolved = app(HelpArticleResolverService::class)->resolve('brand-new-screen', null);

        $this->assertNull($resolved);
    }

    public function test_help_button_renders_inert_icon_without_error_when_no_article_exists(): void
    {
        $response = $this->get(route('landing.show'));

        $response->assertOk();
        $response->assertSee('help-button-landing', false);
    }
}
