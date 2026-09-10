<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * "apagar" a reply is a logical removal-from-display, not a
 * hard delete, so `ForumReply` must support `SoftDeletes`.
 */
class ForumReplyTest extends TestCase
{
    public function test_deleting_a_reply_soft_deletes_it(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $user = User::factory()->inOrg($org->id)->create();
        $topic = ForumTopic::factory()->for($course)->for($user)->inOrg($org->id)->create();
        $reply = ForumReply::factory()->for($topic, 'topic')->for($user)->create();

        $reply->delete();

        $this->assertSoftDeleted($reply);
        $this->assertDatabaseHas('forum_replies', ['id' => $reply->id]);
    }
}
