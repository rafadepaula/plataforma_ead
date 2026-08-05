<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-10 §2.1 — "apagar" a topic is a logical removal-from-display, not a
 * hard delete, so `ForumTopic` must support `SoftDeletes`.
 */
class ForumTopicTest extends TestCase
{
    public function test_deleting_a_topic_soft_deletes_it(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);

        $topic->delete();

        $this->assertSoftDeleted($topic);
        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id]);
    }

    public function test_it_has_a_replies_relationship(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($user)->create();

        $this->assertTrue($topic->replies->contains($reply));
    }

    public function test_it_has_an_organization_relationship(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);

        $this->assertTrue($topic->organization->is($org));
    }
}
