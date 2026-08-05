<?php

namespace Database\Factories;

use App\Models\ForumReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReply>
 *
 * `topic_id`/`user_id` are intentionally left out of the default
 * definition — callers set them explicitly via `->for(ForumTopic::factory(),
 * 'topic')` / `->for(User::factory())`.
 */
class ForumReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }
}
