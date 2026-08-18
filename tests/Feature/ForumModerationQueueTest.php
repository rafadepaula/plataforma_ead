<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * `ReportForumPostAction`'s HTTP wiring: any enrolled
 * Aluno or Org Gestor can denounce a topic/reply via `forum-reports.store`,
 * and the Gestor's `GET forum/moderation` (`forum-moderation.index`)
 * queue, scoped to their own Org, exposes "Descartar"
 * (`forum-moderation.dismiss`) and "Remover Publicação"
 * (`forum-moderation.remove`) actions.
 */
class ForumModerationQueueTest extends TestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_an_enrolled_aluno_can_report_a_topic_and_it_lands_in_the_pending_queue(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        // The HTTP/JS boundary always uses the short `forum_topic`/
        // `forum_reply` strings (`StoreForumReportRequest`'s
        // `in:forum_topic,forum_reply` rule, every "Denunciar" button's
        // `data-postable-type`) — `ForumReportController::resolvePostable()`
        // translates it into the real model before persisting the FQCN
        // (see its docblock).
        $this->actingAs($student)->post(route('forum-reports.store', $course), [
            'postable_type' => 'forum_topic',
            'postable_id' => $topic->id,
            'reason' => 'Conteúdo ofensivo.',
        ])->assertSuccessful();

        $this->assertDatabaseHas('forum_reports', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_an_enrolled_aluno_can_report_a_reply_and_it_lands_in_the_pending_queue(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $this->actingAs($student)->post(route('forum-reports.store', $course), [
            'postable_type' => 'forum_reply',
            'postable_id' => $reply->id,
            'reason' => 'Conteúdo ofensivo.',
        ])->assertSuccessful();

        $this->assertDatabaseHas('forum_reports', [
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_reporting_without_a_reason_fails_validation(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        $this->actingAs($student)->post(route('forum-reports.store', $course), [
            'postable_type' => 'forum_topic',
            'postable_id' => $topic->id,
            'reason' => '',
        ])->assertSessionHasErrors('reason');
    }

    public function test_an_aluno_cannot_view_the_moderation_queue(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)->get(route('forum-moderation.index'))->assertForbidden();
    }

    public function test_gestor_only_sees_pending_reports_from_their_own_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);
        $studentA = $this->enrolledStudent($courseA);
        $studentB = $this->enrolledStudent($courseB);
        $topicA = ForumTopic::factory()->for($courseA)->for($studentA)->create(['org_id' => $courseA->org_id]);
        $topicB = ForumTopic::factory()->for($courseB)->for($studentB)->create(['org_id' => $courseB->org_id]);

        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicA->id,
            'reported_by' => $studentA->id,
            'status' => 'pending',
        ]);
        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicB->id,
            'reported_by' => $studentB->id,
            'status' => 'pending',
        ]);

        $gestorA = $this->actingAsOrgUser($orgA, RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestorA)->get(route('forum-moderation.index'));

        $response->assertOk();
        $response->assertSee('report-row', false);
        $response->assertSee($topicA->content);
        $response->assertDontSee($topicB->content);
    }

    public function test_gestor_can_dismiss_a_report_and_the_post_remains_visible(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)->post(route('forum-moderation.dismiss', $report))->assertRedirect();

        $this->assertDatabaseHas('forum_reports', ['id' => $report->id, 'status' => 'reviewed_dismissed']);
        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'deleted_at' => null]);
    }

    public function test_gestor_can_remove_the_reported_post_which_soft_deletes_it_and_preserves_history(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'content' => 'Conteúdo denunciado.']);
        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)->post(route('forum-moderation.remove', $report))->assertRedirect();

        $this->assertDatabaseHas('forum_reports', ['id' => $report->id, 'status' => 'reviewed_removed']);
        $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'previous_content' => 'Conteúdo denunciado.',
        ]);
    }

    public function test_moderation_queue_does_not_crash_when_the_reported_reply_was_already_removed(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create();
        $reply->delete();

        ForumReport::factory()->create([
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);

        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)->get(route('forum-moderation.index'))->assertOk();
    }
}
