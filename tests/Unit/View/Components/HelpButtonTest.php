<?php

namespace Tests\Unit\View\Components;

use App\Models\HelpArticle;
use App\Models\Organization;
use App\View\Components\HelpButton;
use Tests\TestCase;

/**
 * `<x-help-button key="...">`'s backing class. The
 * only real logic here is resolving *which* `org_id` to hand the
 * `HelpArticleResolverService` for a given viewer: the impersonated org
 * for an Admin, the bound org for a Gestor/Aluno, or `null` for a guest
 * (Landing Page, `/convite/*`, `/validar-certificado/*`) — see
 * `tenancy-conventions`.
 */
class HelpButtonTest extends TestCase
{
    public function test_resolves_org_specific_article_over_global_for_an_org_user(): void
    {
        $org = Organization::factory()->create();

        // `HelpArticle::withoutEvents()` is required here — `OrgScope`'s
        // `creating` hook (see `tenancy-conventions`) always overwrites
        // `org_id` with the acting user's own org, so a `global()` article
        // could not otherwise be created while logged in as $org's gestor.
        $global = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create(['target_page_key' => 'dashboard']));

        $this->actingAsOrgUser($org, 'gestor');

        $orgSpecific = HelpArticle::factory()->forOrg($org)->create(['target_page_key' => 'dashboard']);

        $component = new HelpButton('dashboard');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($orgSpecific));
        $this->assertFalse($component->article->is($global));
    }

    public function test_falls_back_to_global_article_when_no_org_specific_one_exists(): void
    {
        $org = Organization::factory()->create();

        $global = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create(['target_page_key' => 'classroom']));

        $this->actingAsOrgUser($org, 'aluno');

        $component = new HelpButton('classroom');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($global));
    }

    public function test_exposes_null_article_when_none_exists_for_the_key(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $component = new HelpButton('brand-new-screen');

        $this->assertNull($component->article);
    }

    public function test_does_not_throw_for_an_unauthenticated_guest(): void
    {
        $global = HelpArticle::factory()->global()->create(['target_page_key' => 'landing']);

        $component = new HelpButton('landing');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($global));
    }

    public function test_admin_impersonating_an_organization_resolves_the_impersonated_orgs_article(): void
    {
        $adminHome = Organization::factory()->create();
        $impersonated = Organization::factory()->create();

        // Created before `actingAsAdmin()` (and via `withoutEvents()`) so
        // each article keeps its intended `org_id` rather than being
        // overwritten by `OrgScope`'s `creating` hook once the Admin's
        // impersonation session is active.
        $orgSpecific = HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($impersonated)->create(['target_page_key' => 'courses']));
        HelpArticle::withoutEvents(fn () => HelpArticle::factory()->forOrg($adminHome)->create(['target_page_key' => 'courses']));

        $this->actingAsAdmin($impersonated);

        $component = new HelpButton('courses');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($orgSpecific));
    }
}
