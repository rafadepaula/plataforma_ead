<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Events\ForumReplyPosted;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `ForumReplyController`'s HTTP contract: reply
 * creation/edit/removal and the `since_id` polling endpoint consumed by
 * `ForumPolling.js`.
 *
 * The polling payload is a published contract — the frontend rebuilds a
 * reply card from it without a page reload, so it must carry everything
 * the server-rendered partial shows:
 *
 *   {
 *     "data": [
 *       {
 *         "id": 12,
 *         "content": "...",
 *         "created_at": "27/08/2026 14:31",
 *         "created_at_relative": "há 2 minutos",
 *         "initials": "RP",
 *         "role_label": "Aluno",
 *         "user": {"name": "Rafael Paula"}
 *       }
 *     ],
 *     "last_id": 12
 *   }
 */
class ForumReplyControllerTest extends TestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    private function publishedCourse(?Organization $organization = null): Course
    {
        $organization ??= Organization::factory()->create();

        return Course::factory()->create(['org_id' => $organization->id, 'is_published' => true]);
    }

    private function topicFor(Course $course, User $author): ForumTopic
    {
        return ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $course->org_id]);
    }

    public function test_store_persists_the_reply_and_dispatches_the_reply_posted_event(): void
    {
        Event::fake([ForumReplyPosted::class]);

        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $this->actingAs($student)
            ->post(route('forum-replies.store', [$course, $topic]), ['content' => 'Minha contribuição.'])
            ->assertRedirect(route('forum.show', [$course->id, $topic->id]));

        $this->assertDatabaseHas('forum_replies', [
            'topic_id' => $topic->id,
            'user_id' => $student->id,
            'content' => 'Minha contribuição.',
        ]);

        Event::assertDispatched(ForumReplyPosted::class);
    }

    public function test_store_strips_html_from_the_reply_content_before_persisting_it(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $this->actingAs($student)
            ->post(route('forum-replies.store', [$course, $topic]), [
                'content' => '<script>alert(1)</script>Texto limpo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('forum_replies', [
            'topic_id' => $topic->id,
            'content' => 'alert(1)Texto limpo',
        ]);
        $this->assertDatabaseMissing('forum_replies', ['content' => '<script>alert(1)</script>Texto limpo']);
    }

    public function test_store_rejects_an_empty_reply(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $this->actingAs($student)
            ->post(route('forum-replies.store', [$course, $topic]), ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('forum_replies', 0);
    }

    public function test_edit_screen_renders_for_the_reply_author_with_the_current_content(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Minha resposta original.']);

        $this->actingAs($student)
            ->get(route('forum-replies.edit', [$course, $topic, $reply]))
            ->assertOk()
            ->assertSee('Minha resposta original.')
            ->assertSee(route('forum-replies.update', [$course, $topic, $reply]), false);
    }

    public function test_edit_screen_refuses_an_unrelated_aluno_of_the_same_course(): void
    {
        $course = $this->publishedCourse();
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $author);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($author)->create();

        $this->actingAs($otherStudent)
            ->get(route('forum-replies.edit', [$course, $topic, $reply]))
            ->assertForbidden();
    }

    public function test_update_records_an_edit_history_row_and_stamps_edited_at(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Conteúdo original.']);

        $this->actingAs($student)
            ->put(route('forum-replies.update', [$course, $topic, $reply]), ['content' => 'Conteúdo revisado.'])
            ->assertRedirect(route('forum.show', [$course->id, $topic->id]));

        $this->assertDatabaseHas('forum_replies', ['id' => $reply->id, 'content' => 'Conteúdo revisado.']);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
            'previous_content' => 'Conteúdo original.',
        ]);
        $this->assertNotNull($reply->fresh()->edited_at);
    }

    public function test_destroy_is_allowed_to_the_author_and_to_a_same_org_gestor_but_refused_to_another_aluno(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $author);

        $refusedReply = ForumReply::factory()->for($topic, 'topic')->for($author)->create();
        $this->actingAs($otherStudent)
            ->delete(route('forum-replies.destroy', [$course, $topic, $refusedReply]))
            ->assertForbidden();
        $this->assertNotSoftDeleted('forum_replies', ['id' => $refusedReply->id]);

        $this->actingAs($author)
            ->delete(route('forum-replies.destroy', [$course, $topic, $refusedReply]))
            ->assertRedirect();
        $this->assertSoftDeleted('forum_replies', ['id' => $refusedReply->id]);

        $moderatedReply = ForumReply::factory()->for($topic, 'topic')->for($author)->create();
        $gestor = $this->actingAsOrgUser($organization, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)
            ->delete(route('forum-replies.destroy', [$course, $topic, $moderatedReply]))
            ->assertRedirect();
        $this->assertSoftDeleted('forum_replies', ['id' => $moderatedReply->id]);
    }

    public function test_fetch_new_returns_only_later_replies_ordered_ascending(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $replies = collect(range(1, 4))->map(
            fn (): ForumReply => ForumReply::factory()->for($topic, 'topic')->for($student)->create()
        );

        $response = $this->actingAs($student)->getJson(
            route('forum-replies.fetch', [$course, $topic]).'?since_id='.$replies[1]->id
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$replies[2]->id, $replies[3]->id], $ids);
        $this->assertSame($replies[3]->id, $response->json('last_id'));
    }

    public function test_fetch_new_caps_a_long_backlog_at_fifty_replies_per_call(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        ForumReply::factory()->count(55)->for($topic, 'topic')->for($student)->create();

        $response = $this->actingAs($student)->getJson(route('forum-replies.fetch', [$course, $topic]));

        $response->assertOk();
        $this->assertCount(50, $response->json('data'));
        $this->assertSame(
            collect($response->json('data'))->pluck('id')->last(),
            $response->json('last_id')
        );
    }

    public function test_fetch_new_carries_the_author_initials_and_role_label_needed_to_rebuild_the_reply_card(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null, 'name' => 'Maria da Silva Souza']);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $topic = $this->topicFor($course, $student);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Olá turma.']);

        $response = $this->actingAs($student)->getJson(route('forum-replies.fetch', [$course, $topic]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $reply->id);
        $response->assertJsonPath('data.0.content', 'Olá turma.');
        $response->assertJsonPath('data.0.created_at', $reply->created_at->format('d/m/Y H:i'));
        // The partial shows the relative form as visible text and keeps the
        // absolute one in `title=`; the payload must ship both.
        $response->assertJsonPath('data.0.created_at_relative', $reply->created_at->diffForHumans());
        $response->assertJsonPath('data.0.initials', 'MD');
        $response->assertJsonPath('data.0.role_label', 'Aluno');
        $response->assertJsonPath('data.0.user.name', 'Maria da Silva Souza');
        $response->assertJsonPath('last_id', $reply->id);
    }

    public function test_fetch_new_labels_a_gestor_reply_with_the_gestor_role(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $organization->id, 'name' => 'Ana Tutora']);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        ForumReply::factory()->for($topic, 'topic')->for($gestor)->create();

        $response = $this->actingAs($student)->getJson(route('forum-replies.fetch', [$course, $topic]));

        $response->assertOk();
        $response->assertJsonPath('data.0.initials', 'AT');
        $response->assertJsonPath('data.0.role_label', 'Gestor');
    }

    public function test_fetch_new_treats_a_missing_or_garbage_since_id_as_zero(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $this->actingAs($student)
            ->getJson(route('forum-replies.fetch', [$course, $topic]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $reply->id);

        $this->actingAs($student)
            ->getJson(route('forum-replies.fetch', [$course, $topic]).'?since_id=abc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reply->id);
    }

    public function test_fetch_new_returns_an_empty_payload_with_a_zero_last_id_when_nothing_is_new(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $response = $this->actingAs($student)->getJson(
            route('forum-replies.fetch', [$course, $topic]).'?since_id='.$reply->id
        );

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('last_id'));
    }

    public function test_fetch_new_is_blocked_for_an_aluno_without_an_active_enrollment(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        /** @var User $outsider */
        $outsider = User::factory()->create(['org_id' => null]);
        $outsider->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($outsider)
            ->getJson(route('forum-replies.fetch', [$course, $topic]))
            ->assertForbidden();
    }

    public function test_a_gestor_from_another_org_cannot_reach_the_reply_polling_endpoint(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Conteúdo de outra organização.']);
        $foreignGestor = $this->actingAsOrgUser(Organization::factory()->create(), RolesEnum::GESTOR->value);

        $this->actingAs($foreignGestor)
            ->getJson(route('forum-replies.fetch', [$course, $topic]))
            ->assertForbidden();
    }

    /**
     * `student.enrolled` already 403s a cross-org Gestor before the
     * controller runs, so that guard is dropped here to reach the
     * authorization `fetchNew()` performs itself: `resolveTopic()`'s
     * by-name `withoutGlobalScope('org')` bypass must still RESOLVE the
     * foreign topic (a 403, never a silent 404), and `ForumTopicPolicy`
     * `view` — the Gestor `org_id` comparison inside `hasCourseAccess()` —
     * must deny it. Drop either leg and the endpoint starts streaming
     * another Org's reply content, author names, initials and role labels
     * to a Gestor of Organization B.
     */
    public function test_fetch_new_refuses_a_cross_org_gestor_on_the_topic_view_policy_behind_the_org_scope_bypass(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Conteúdo de outra organização.']);
        $foreignGestor = $this->actingAsOrgUser(Organization::factory()->create(), RolesEnum::GESTOR->value);

        $this->withoutMiddleware(EnsureStudentIsEnrolled::class);

        $response = $this->actingAs($foreignGestor)
            ->getJson(route('forum-replies.fetch', [$course, $topic]));

        $response->assertForbidden();
        $this->assertStringNotContainsString('Conteúdo de outra organização.', $response->getContent());
    }

    /**
     * The polling client has no removal path — a reply it already
     * rendered stays on screen until reload. `fetchNew` must therefore
     * never hand back a reply that moderation soft-deleted, or a stale
     * `since_id` from a long-idle tab would re-inject it as if it were
     * brand new.
     */
    public function test_fetch_new_never_returns_a_reply_that_was_removed_after_it_was_rendered(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $kept = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Continua visível.']);
        $removed = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Removida pela moderação.']);
        $removed->delete();

        $response = $this->actingAs($student)
            ->getJson(route('forum-replies.fetch', [$course, $topic]).'?since_id=0');

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame([$kept->id], array_column($payload['data'], 'id'));
        $this->assertSame($kept->id, $payload['last_id']);
    }

    /**
     * A tab left open on a thread that gets removed must stop being fed
     * by the poll loop rather than keep streaming replies out of a
     * soft-deleted topic.
     */
    public function test_fetch_new_reports_a_removed_topic_as_missing(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $topic->delete();

        $this->actingAs($student)
            ->getJson(route('forum-replies.fetch', [$course, $topic]).'?since_id=0')
            ->assertNotFound();
    }
}
