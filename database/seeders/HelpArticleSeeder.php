<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * SPEC-11 (RF12/RN05) & SPEC-18 (UC02) — seeds the global (org_id = null)
 * `HelpArticle` rows resolved by `HelpArticleResolverService` for screens
 * that don't yet have org-specific overrides authored.
 *
 * Uses `withoutEvents()` (see `help-conventions`) so `OrgScope`'s
 * `creating` hook doesn't stamp an active-org `org_id` onto what must
 * stay a global article, and `firstOrCreate` keyed on `target_page_key`
 * so re-running the seeder is idempotent.
 */
class HelpArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $articles = [
            [
                'target_page_key' => 'profile.edit',
                'title' => 'Como editar meu perfil',
                'category' => 'geral',
                'content' => "Nesta tela você pode atualizar seus dados cadastrais (nome, e-mail e CPF) e trocar sua senha.\n\n".
                    "Para atualizar seus dados, preencha o formulário \"Informações do Perfil\" e clique em \"Salvar Alterações\".\n\n".
                    'Para trocar sua senha, informe sua senha atual e a nova senha no formulário "Atualizar Senha". Ao confirmar, todas as suas outras sessões ativas serão encerradas por segurança.',
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::withoutEvents(function () use ($article): void {
                HelpArticle::query()->firstOrCreate(
                    [
                        'org_id' => null,
                        'target_page_key' => $article['target_page_key'],
                    ],
                    [
                        'title' => $article['title'],
                        'slug' => str($article['target_page_key'].'-'.$article['title'])->slug(),
                        'category' => $article['category'],
                        'content' => $article['content'],
                    ]
                );
            });
        }
    }
}
