<?php

namespace Database\Factories;

use App\Models\ForumPostEdit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPostEdit>
 *
 * `postable_type`/`postable_id` are intentionally left out of the default
 * definition (no DB FK/morphTo backs this pseudo-polymorphic pair) —
 * callers set them explicitly, e.g. `['postable_type' => ForumTopic::class,
 * 'postable_id' => $topic->id]`. `editor_user_id` is also left out —
 * callers set it via `->for(User::factory(), 'editor')`.
 */
class ForumPostEditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'previous_content' => fake()->paragraph(),
            'edited_at' => now(),
        ];
    }
}
