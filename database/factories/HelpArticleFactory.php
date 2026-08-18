<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpArticle>
 *
 * `org_id` is intentionally left out of the default definition (see
 * `tenancy-conventions`) — since `HelpArticle.org_id` is nullable by
 * design , leaving it unset produces a *global* article,
 * which doubles as the natural default here. Use `global()` for
 * explicitness in a test, or `forOrg()` to build an org-specific article.
 */
class HelpArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => str(fake()->unique()->slug(3))->limit(220, ''),
            'category' => fake()->randomElement(['geral', 'cursos', 'certificados', 'forum', 'matriculas']),
            'target_page_key' => fake()->unique()->regexify('[a-z]+\.[a-z]+'),
            'content' => fake()->paragraphs(3, true),
        ];
    }

    /**
     * Force this article to be global (`org_id` = null), visible to every
     * Organization.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes): array => ['org_id' => null]);
    }

    /**
     * Attach this article to a specific Organization, making it
     * org-specific and only resolvable for that tenant.
     */
    public function forOrg(Organization $org): static
    {
        return $this->state(fn (array $attributes): array => ['org_id' => $org->id]);
    }
}
