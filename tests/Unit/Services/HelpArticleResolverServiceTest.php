<?php

namespace Tests\Unit\Services;

use App\Models\HelpArticle;
use App\Models\Organization;
use App\Services\HelpArticleResolverService;
use Tests\TestCase;

/**
 * SPEC-11 (RF12/RN05) — `HelpArticleResolverService::resolve()` is the
 * single source of truth for the org-specific-first, global-fallback
 * lookup used by the contextual `<x-help-button>`. It must bypass
 * `OrgScope` entirely (both org-specific and global rows must be visible
 * regardless of the request's `Auth::user()`/`active_org_id` context) and
 * must never throw for anonymous/public contexts (`$orgId = null`).
 */
class HelpArticleResolverServiceTest extends TestCase
{
    private HelpArticleResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HelpArticleResolverService;
    }

    public function test_it_resolves_the_org_specific_article_when_one_exists(): void
    {
        $org = Organization::factory()->create();
        $orgSpecific = HelpArticle::factory()->forOrg($org)->create(['target_page_key' => 'dashboard.gestor']);
        HelpArticle::factory()->global()->create(['target_page_key' => 'dashboard.gestor']);

        $resolved = $this->service->resolve('dashboard.gestor', $org->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($orgSpecific));
    }

    public function test_it_falls_back_to_the_global_article_when_no_org_specific_one_exists(): void
    {
        $org = Organization::factory()->create();
        $global = HelpArticle::factory()->global()->create(['target_page_key' => 'dashboard.gestor']);

        $resolved = $this->service->resolve('dashboard.gestor', $org->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($global));
    }

    public function test_it_returns_null_when_neither_an_org_specific_nor_a_global_article_exists(): void
    {
        $org = Organization::factory()->create();

        $resolved = $this->service->resolve('some.unauthored.page', $org->id);

        $this->assertNull($resolved);
    }

    public function test_it_resolves_only_the_global_article_for_an_anonymous_null_org_context(): void
    {
        $global = HelpArticle::factory()->global()->create(['target_page_key' => 'landing']);
        $otherOrg = Organization::factory()->create();
        HelpArticle::factory()->forOrg($otherOrg)->create(['target_page_key' => 'landing']);

        $resolved = $this->service->resolve('landing', null);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($global));
    }

    public function test_it_does_not_leak_another_organizations_specific_article(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        HelpArticle::factory()->forOrg($otherOrg)->create(['target_page_key' => 'dashboard.gestor']);

        $resolved = $this->service->resolve('dashboard.gestor', $org->id);

        $this->assertNull($resolved);
    }

    public function test_hard_deleting_the_org_specific_article_falls_back_to_global_on_the_next_resolution(): void
    {
        // `help_articles` has no soft-deletes (see SPEC-00 §2.1.20's
        // SoftDelete table list) — removing an org-specific override is a
        // hard delete, and the very next `resolve()` call must cleanly
        // fall back to the global article, with no stale cache.
        $org = Organization::factory()->create();
        $global = HelpArticle::factory()->global()->create(['target_page_key' => 'dashboard.gestor']);
        $orgSpecific = HelpArticle::factory()->forOrg($org)->create(['target_page_key' => 'dashboard.gestor']);

        $this->assertTrue($this->service->resolve('dashboard.gestor', $org->id)->is($orgSpecific));

        $orgSpecific->delete();

        $resolved = $this->service->resolve('dashboard.gestor', $org->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($global));
    }
}
