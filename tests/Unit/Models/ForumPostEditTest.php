<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\ForumPostEdit;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-10 §2.2 — `forum_post_edits`/`forum_reports` intentionally have no
 * DB FK/morphTo for `postable_type`/`postable_id`; `postable()` resolves
 * the pseudo-polymorphic pair at the application layer, including a
 * soft-deleted target (moderation must not crash on it — see the plan's
 * edge cases).
 */
class ForumPostEditTest extends TestCase
{
    public function test_postable_resolves_a_forum_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);

        $edit = ForumPostEdit::factory()->for($user, 'editor')->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
        ]);

        $this->assertTrue($edit->postable()->is($topic));
    }

    public function test_postable_resolves_a_forum_reply(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($user)->create();

        $edit = ForumPostEdit::factory()->for($user, 'editor')->create([
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
        ]);

        $this->assertTrue($edit->postable()->is($reply));
    }

    public function test_postable_resolves_a_soft_deleted_target(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);
        $topic->delete();

        $report = ForumReport::factory()->for($user, 'reporter')->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
        ]);

        $this->assertTrue($report->postable()->is($topic));
    }
}
