<?php

namespace Tests\Unit\Models;

use App\Models\HelpArticle;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * SPEC-11 (RF12/RN05) — `help_articles.slug` schema-level guarantees.
 */
class HelpArticleTest extends TestCase
{
    public function test_slug_is_globally_unique_even_across_different_organizations(): void
    {
        // `target_page_key` + `org_id` differ, but a human-chosen `slug`
        // is a single global column (`unique()` on the migration, no
        // per-org composite) — two different Organizations cannot both
        // author an article with the same slug. Flagged in the tech-refine
        // plan as a scope question for any future admin authoring UI; this
        // test only pins down the schema-level guarantee that already
        // exists today.
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        HelpArticle::factory()->forOrg($orgA)->create(['slug' => 'como-usar-o-forum']);

        $this->expectException(QueryException::class);

        HelpArticle::factory()->forOrg($orgB)->create(['slug' => 'como-usar-o-forum']);
    }

    public function test_organization_relation_resolves_the_owning_org_for_a_for_org_article(): void
    {
        $org = Organization::factory()->create();
        $article = HelpArticle::factory()->forOrg($org)->create();

        $this->assertTrue($article->organization()->is($org));
    }
}
