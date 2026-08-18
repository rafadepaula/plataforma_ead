<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * `ForumReport`'s `reporter`/`reviewer` relationships and
 * its `postable()` pseudo-polymorphic resolver (kept in sync with
 * `ForumPostEdit::postable()`).
 */
class ForumReportTest extends TestCase
{
    public function test_it_has_reporter_and_reviewer_relationships(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $author = User::factory()->create(['org_id' => $org->id]);
        $reporter = User::factory()->create(['org_id' => $org->id]);
        $reviewer = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $org->id]);

        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $reporter->id,
            'reviewed_by' => $reviewer->id,
        ]);

        $this->assertTrue($report->reporter->is($reporter));
        $this->assertTrue($report->reviewer->is($reviewer));
    }

    public function test_postable_resolves_the_reported_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $user = User::factory()->create(['org_id' => $org->id]);
        $topic = ForumTopic::factory()->for($course)->for($user)->create(['org_id' => $org->id]);

        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $user->id,
        ]);

        $this->assertTrue($report->postable()->is($topic));
    }
}
