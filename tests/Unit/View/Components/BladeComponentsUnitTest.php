<?php

namespace Tests\Unit\View\Components;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\Organization;
use App\Models\User;
use App\View\Components\HelpButton;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BladeComponentsUnitTest extends TestCase
{
    public function test_help_button_resolves_article_for_admin_with_active_impersonated_org(): void
    {
        $org = Organization::factory()->create();

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        Auth::login($admin);

        HelpArticle::withoutEvents(function () use ($org, &$orgArticle) {
            HelpArticle::factory()->global()->create([
                'target_page_key' => 'courses.index',
                'title' => 'Artigo Global',
            ]);

            $orgArticle = HelpArticle::factory()->forOrg($org)->create([
                'target_page_key' => 'courses.index',
                'title' => 'Artigo Org Específico',
            ]);
        });

        session(['active_org_id' => $org->id]);

        $component = new HelpButton('courses.index');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($orgArticle));
        $this->assertSame('Artigo Org Específico', $component->article->title);
    }

    public function test_help_button_resolves_article_for_org_user(): void
    {
        $org = Organization::factory()->create();

        $user = User::factory()->create(['org_id' => $org->id]);
        $user->assignRole(RolesEnum::GESTOR->value);
        Auth::login($user);

        $orgArticle = HelpArticle::factory()->forOrg($org)->create([
            'target_page_key' => 'dashboard',
            'title' => 'Ajuda do Gestor',
        ]);

        $component = new HelpButton('dashboard');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($orgArticle));
    }

    public function test_help_button_resolves_global_article_when_no_org_article_exists(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id]);
        $user->assignRole(RolesEnum::ALUNO->value);
        Auth::login($user);

        $global = HelpArticle::factory()->global()->create([
            'target_page_key' => 'student.quizzes.show',
            'title' => 'Instruções da Prova',
        ]);

        $component = new HelpButton('student.quizzes.show');

        $this->assertNotNull($component->article);
        $this->assertTrue($component->article->is($global));
    }

    public function test_no_component_emits_forbidden_red_orange_yellow_color_codes(): void
    {
        $forbiddenPatterns = [
            '#ec3013',
            '#ff0000',
            '#f00',
            'rgb(255, 0, 0)',
            '#ffa500',
            '#ffff00',
        ];

        $componentSnippets = [
            '<x-ui.button variant="danger">Excluir</x-ui.button>',
            '<x-ui.badge variant="accent-2">Crítico</x-ui.badge>',
            '<x-ui.alert variant="danger">Erro de sistema</x-ui.alert>',
            '<x-ui.alert variant="warning">Aviso</x-ui.alert>',
            '<x-ui.progress :value="50" variant="danger" />',
            '<x-ui.progress :value="50" variant="warning" />',
            '<x-ui.confirm-modal id="c1" action="/test" method="DELETE" />',
            '<x-ui.delete-button action="/test" />',
            '<x-ui.input name="test" label="Campo" error="Erro no campo" />',
            '<x-ui.stat-card kicker="Métrica" :no-data="true" />',
        ];

        foreach ($componentSnippets as $snippet) {
            $rendered = (string) $this->blade($snippet);
            foreach ($forbiddenPatterns as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $rendered,
                    "Component snippet [{$snippet}] leaked forbidden color [{$forbidden}]."
                );
            }
        }
    }

    public function test_page_header_renders_an_h1_by_default(): void
    {
        $rendered = (string) $this->blade('<x-layout.page-header title="Cursos" />');

        $this->assertStringContainsString('<h1 class="h3 mb-0">Cursos</h1>', $rendered);
    }

    public function test_page_header_renders_the_requested_heading_level(): void
    {
        $withPrefix = (string) $this->blade('<x-layout.page-header title="Entrar na plataforma" kicker="Acesso" level="h2" />');
        $withNumber = (string) $this->blade('<x-layout.page-header title="Entrar na plataforma" level="2" />');

        foreach ([$withPrefix, $withNumber] as $rendered) {
            $this->assertStringContainsString('<h2 class="h3 mb-0">Entrar na plataforma</h2>', $rendered);
            $this->assertStringNotContainsString('<h1', $rendered);
        }

        $this->assertStringContainsString('Acesso', $withPrefix);
        $this->assertStringNotContainsString('level=', $withPrefix);
    }

    public function test_guest_panel_renders_the_default_headline_lead_and_current_year(): void
    {
        $rendered = (string) $this->blade('<x-layout.guest-panel />');

        $this->assertStringContainsString('Acesse a plataforma', $rendered);
        $this->assertStringContainsString(
            'Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.',
            $rendered
        );
        $this->assertStringContainsString('&copy; '.date('Y'), $rendered);
        $this->assertStringNotContainsString('avaliações interativas', $rendered);
    }

    public function test_guest_panel_accepts_tenant_name_headline_and_lead_props(): void
    {
        $rendered = (string) $this->blade(
            '<x-layout.guest-panel tenant-name="Conselho Regional" headline="Bem-vindo" lead="Texto institucional." />'
        );

        $this->assertStringContainsString('Conselho Regional', $rendered);
        $this->assertStringContainsString('>CR<', $rendered);
        $this->assertStringContainsString('Bem-vindo', $rendered);
        $this->assertStringContainsString('Texto institucional.', $rendered);
        $this->assertStringNotContainsString('provas interativas', $rendered);
    }

    public function test_guest_panel_falls_back_to_the_session_tenant_name(): void
    {
        session(['tenant_name' => 'Instituto Alfa Beta']);

        $rendered = (string) $this->blade('<x-layout.guest-panel />');

        $this->assertStringContainsString('Instituto Alfa Beta', $rendered);
        $this->assertStringContainsString('>IA<', $rendered);
    }

    public function test_guest_panel_can_render_only_the_tenant_brand_for_the_mobile_column(): void
    {
        session(['tenant_name' => 'Conselho Regional']);

        $rendered = (string) $this->blade('<x-layout.guest-panel brand-only class="d-lg-none" />');

        $this->assertStringContainsString('Conselho Regional', $rendered);
        $this->assertStringContainsString('>CR<', $rendered);
        $this->assertStringContainsString('d-lg-none', $rendered);
        $this->assertStringNotContainsString('Acesse a plataforma', $rendered);
        $this->assertStringNotContainsString('<h1', $rendered);
        $this->assertStringNotContainsString('d-lg-flex', $rendered);
    }
}
