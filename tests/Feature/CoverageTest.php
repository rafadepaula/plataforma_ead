<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use Database\Seeders\HelpArticleSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HelpArticleSeeder::class);
    }

    public function test_every_target_page_key_in_config_has_a_global_help_article(): void
    {
        $keys = (array) config('help.target_page_keys', []);
        $this->assertNotEmpty($keys, 'Target page keys in config/help.php must not be empty.');

        $existingKeys = HelpArticle::withoutGlobalScopes()
            ->whereNull('org_id')
            ->whereIn('target_page_key', $keys)
            ->pluck('target_page_key')
            ->all();

        $missing = array_diff($keys, $existingKeys);

        $this->assertEmpty(
            $missing,
            'Missing global help articles for target_page_keys: '.implode(', ', $missing)
        );
    }

    public function test_every_authenticated_and_public_get_route_is_covered_by_catalog_or_explicitly_ignored(): void
    {
        $catalogKeys = (array) config('help.target_page_keys', []);
        $ignoredRoutes = (array) config('help.ignored_routes', []);

        $routes = Route::getRoutes()->getRoutes();

        $uncovered = [];

        foreach ($routes as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();

            if (empty($name)) {
                continue;
            }

            // Exclude third-party, testing, or internal debugging routes
            if (str_starts_with($name, 'ignition.') || str_starts_with($name, '_debugbar') || str_starts_with($name, 'dusk.')) {
                continue;
            }

            // Check if explicitly ignored
            if (in_array($name, $ignoredRoutes, true)) {
                continue;
            }

            if (! in_array($name, $catalogKeys, true)) {
                $uncovered[] = $name;
            }
        }

        $this->assertEmpty(
            $uncovered,
            'Found GET routes without help article coverage in config/help.php: '.implode(', ', $uncovered)
        );
    }

    public function test_standalone_keys_are_present_in_catalog_and_have_global_articles(): void
    {
        $standaloneKeys = (array) config('help.standalone_keys', []);
        $catalogKeys = (array) config('help.target_page_keys', []);

        $missingInCatalog = array_diff($standaloneKeys, $catalogKeys);
        $this->assertEmpty(
            $missingInCatalog,
            'Standalone keys missing from config/help.php target_page_keys: '.implode(', ', $missingInCatalog)
        );

        foreach ($standaloneKeys as $key) {
            $exists = HelpArticle::withoutGlobalScopes()
                ->whereNull('org_id')
                ->where('target_page_key', $key)
                ->exists();

            $this->assertTrue($exists, "Global help article for standalone key [{$key}] must exist in database.");
        }
    }

    public function test_every_article_follows_mandated_markdown_structure(): void
    {
        $articles = HelpArticle::withoutGlobalScopes()
            ->whereNull('org_id')
            ->get();

        $this->assertGreaterThanOrEqual(60, $articles->count());

        foreach ($articles as $article) {
            $this->assertStringContainsString(
                '## Para que serve',
                $article->content,
                "Article [{$article->slug}] must contain '## Para que serve'"
            );
            $this->assertStringContainsString(
                '## Passo a passo',
                $article->content,
                "Article [{$article->slug}] must contain '## Passo a passo'"
            );
            $this->assertStringContainsString(
                '## Regras e limites',
                $article->content,
                "Article [{$article->slug}] must contain '## Regras e limites'"
            );
            $this->assertStringContainsString(
                '## Dúvidas comuns',
                $article->content,
                "Article [{$article->slug}] must contain '## Dúvidas comuns'"
            );
            $this->assertStringNotContainsString(
                '<script',
                $article->content,
                "Article [{$article->slug}] must not contain raw <script> tags."
            );
        }
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_articles(): void
    {
        $countBefore = HelpArticle::withoutGlobalScopes()->whereNull('org_id')->count();

        $this->seed(HelpArticleSeeder::class);

        $countAfter = HelpArticle::withoutGlobalScopes()->whereNull('org_id')->count();

        $this->assertSame($countBefore, $countAfter, 'Running HelpArticleSeeder multiple times must be strictly idempotent.');
    }

    public function test_missing_article_detection_fails_when_key_is_absent(): void
    {
        $missingKey = 'non.existent.route.key';
        $keys = [$missingKey];

        $existing = HelpArticle::withoutGlobalScopes()
            ->whereNull('org_id')
            ->whereIn('target_page_key', $keys)
            ->pluck('target_page_key')
            ->all();

        $missing = array_diff($keys, $existing);

        $this->assertSame([$missingKey], array_values($missing));
    }

    public function test_unregistered_get_route_detection_identifies_uncovered_routes(): void
    {
        $catalogKeys = (array) config('help.target_page_keys', []);
        $ignoredRoutes = (array) config('help.ignored_routes', []);

        $fictitiousRoute = 'new.fictitious.screen.index';

        $isCovered = in_array($fictitiousRoute, $catalogKeys, true) || in_array($fictitiousRoute, $ignoredRoutes, true);

        $this->assertFalse($isCovered, 'A fictitious new GET route must be flagged as uncovered.');
    }
}
