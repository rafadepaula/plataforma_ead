<?php

namespace Database\Factories;

use App\Models\ForumReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReport>
 *
 * `postable_type`/`postable_id` are intentionally left out of the default
 * definition (no DB FK/morphTo backs this pseudo-polymorphic pair) —
 * callers set them explicitly, e.g. `['postable_type' => ForumTopic::class,
 * 'postable_id' => $topic->id]`. `reported_by` is also left out — callers
 * set it via `->for(User::factory(), 'reporter')`.
 */
class ForumReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reason' => fake()->sentence(10),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed_dismissed',
            'reviewed_at' => now(),
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed_removed',
            'reviewed_at' => now(),
        ]);
    }
}
