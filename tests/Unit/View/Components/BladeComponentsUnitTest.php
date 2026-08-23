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
}
