<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\Organization;
use Tests\TestCase;

/**
 * `<x-help-button>` must be present on every
 * authenticated screen (wired once into `components/layout/topbar.blade.php`,
 * present in every `layouts.app`-based view) across the 3 roles, and must
 * render the resolved `HelpArticle`'s content once opened.
 */
class HelpCenterTest extends TestCase
{
    public function test_help_button_renders_on_admin_screen_with_resolved_article(): void
    {
        // Created via `withoutEvents()` before/independently of the acting
        // Admin session — `OrgScope`'s `creating` hook (see
        // `tenancy-conventions`) would otherwise overwrite `org_id` (or
        // throw `UnresolvedOrgContextException`, since a system Admin
        // with no active "Impersonate Org" session has neither its own
        // `org_id` nor a `session('active_org_id')`) rather than leaving
        // this article global.
        HelpArticle::withoutEvents(fn () => HelpArticle::factory()->global()->create([
            'target_page_key' => 'organizations.index',
            'title' => 'Como gerenciar organizações',
            'content' => 'Conteúdo de ajuda para o Admin.',
        ]));

        $this->actingAsAdmin();

        $response = $this->get(route('organizations.index'));

        $response->assertOk();
        $response->assertSee('help-button-organizations.index', false);
        $response->assertSee('Como gerenciar organizações');
        $response->assertSee('Conteúdo de ajuda para o Admin.');
    }

    public function test_help_button_renders_on_gestor_screen_with_resolved_article(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        HelpArticle::factory()->forOrg($org)->create([
            'target_page_key' => 'courses.index',
            'title' => 'Como gerenciar cursos',
            'content' => 'Conteúdo de ajuda para o Gestor.',
        ]);

        $response = $this->get(route('courses.index'));

        $response->assertOk();
        $response->assertSee('help-button-courses.index', false);
        $response->assertSee('Como gerenciar cursos');
        $response->assertSee('Conteúdo de ajuda para o Gestor.');
    }

    public function test_help_button_renders_on_aluno_screen_with_resolved_article(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        HelpArticle::factory()->forOrg($org)->create([
            'target_page_key' => 'student.courses.index',
            'title' => 'Como acessar meus cursos',
            'content' => 'Conteúdo de ajuda para o Aluno.',
        ]);

        $response = $this->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee('help-button-student.courses.index', false);
        $response->assertSee('Como acessar meus cursos');
        $response->assertSee('Conteúdo de ajuda para o Aluno.');
    }

    public function test_help_button_renders_placeholder_when_no_article_exists_for_the_screen(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('organizations.index'));

        $response->assertOk();

        // Scoped to the specific help-button element (rather than a bare
        // `assertSee()` anywhere on the page) so this stays resilient to
        // unrelated buttons elsewhere on the screen and only passes when
        // *this* button actually renders the active placeholder branch.
        preg_match(
            '/<button[^>]*dusk="help-button-organizations\.index"[^>]*>/',
            $response->getContent(),
            $matches
        );

        $this->assertNotEmpty($matches, 'help-button-organizations.index element not found in response.');
        $this->assertStringNotContainsString('disabled', $matches[0]);

        $response->assertSee('help-placeholder-content-organizations.index', false);
        $response->assertSee('Estamos preparando');
    }
}
