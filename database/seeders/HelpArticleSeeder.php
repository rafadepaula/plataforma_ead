<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * Seeds global (org_id = null) HelpArticle records for 100% of the platform's screens.
 *
 * Uses `withoutEvents()` so `OrgScope`'s creating hook does not stamp an active
 * tenant onto global articles or throw `UnresolvedOrgContextException`.
 * Uses `updateOrCreate` keyed on `slug` for idempotent execution across environments.
 */
class HelpArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $academic = require __DIR__.'/data/help_articles_academic.php';
        $evaluations = require __DIR__.'/data/help_articles_evaluations.php';
        $student = require __DIR__.'/data/help_articles_student.php';
        $adminPublic = require __DIR__.'/data/help_articles_admin_public.php';

        $allArticles = array_merge($academic, $evaluations, $student, $adminPublic);

        foreach ($allArticles as $article) {
            HelpArticle::withoutEvents(function () use ($article): void {
                HelpArticle::query()->updateOrCreate(
                    [
                        'slug' => $article['slug'],
                    ],
                    [
                        'org_id' => null,
                        'title' => $article['title'],
                        'category' => $article['category'],
                        'target_page_key' => $article['target_page_key'],
                        'audience' => $article['audience'] ?? 'aluno',
                        'content' => $article['content'],
                    ]
                );
            });
        }
    }
}
