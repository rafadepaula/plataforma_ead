<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * the Aluno/Gestor-facing forum HTTP layer: topic
 * list ordering (pinned first), topic/reply CRUD, enrollment gating, and
 * the `fetchNewReplies` since_id-based polling endpoint (§2). Wired
 * against Bucket 2's `forum.*`/`forum-replies.*` route contract:
 *
 *   GET    courses/{course}/forum                              forum.index
 *   POST   courses/{course}/forum                               forum.store
 *   GET    courses/{course}/forum/topics/{topic}                 forum.show
 *   PUT    courses/{course}/forum/topics/{topic}                  forum.update
 *   DELETE courses/{course}/forum/topics/{topic}                  forum.destroy
 *   POST   courses/{course}/forum/topics/{topic}/pin              forum.pin
 *   POST   courses/{course}/forum/topics/{topic}/replies          forum-replies.store
 *   PUT    courses/{course}/forum/topics/{topic}/replies/{reply}  forum-replies.update
 *   DELETE courses/{course}/forum/topics/{topic}/replies/{reply}  forum-replies.destroy
 *   GET    courses/{course}/forum/topics/{topic}/replies/fetch    forum-replies.fetch
 */
class ForumTopicTest extends TestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_pinned_topics_are_listed_before_newer_unpinned_ones(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $older = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'created_at' => now()->subDays(2)]);
        $newer = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'created_at' => now()->subDay()]);
        $pinned = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'is_pinned' => true,
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $content = $response->getContent();
        $pinnedPos = strpos($content, 'topic-row-'.$pinned->id);
        $newerPos = strpos($content, 'topic-row-'.$newer->id);
        $olderPos = strpos($content, 'topic-row-'.$older->id);

        $this->assertNotFalse($pinnedPos);
        $this->assertLessThan($newerPos, $pinnedPos);
        $this->assertLessThan($olderPos, $newerPos);
    }

    public function test_a_non_enrolled_aluno_is_sent_back_to_the_catalog_instead_of_the_forum(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        /** @var User $outsider */
        $outsider = User::factory()->create(['org_id' => null]);
        $outsider->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($outsider)->get(route('forum.index', $course));

        $response->assertRedirect(route('student.courses.index'));
        $response->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_an_enrolled_aluno_can_create_a_topic_and_reply_to_it(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)->post(route('forum.store', $course), [
            'title' => 'Dúvida sobre o módulo 1',
            'content' => 'Alguém pode explicar melhor o conceito X?',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_topics', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'title' => 'Dúvida sobre o módulo 1',
        ]);

        // `ForumTopic` carries `OrgScope`, and the currently `actingAs`
        // user here is a multi-org Aluno (`org_id === null`) — a scoped
        // query would zero out every row (see `OrgScope::bootOrgScope()`),
        // so this lookup bypasses the scope, mirroring
        // `ForumTopicController::resolveTopic()`'s own convention.
        $topic = ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();

        $this->actingAs($student)->post(route('forum-replies.store', [$course, $topic]), [
            'content' => 'Aqui está minha resposta.',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_replies', [
            'topic_id' => $topic->id,
            'user_id' => $student->id,
            'content' => 'Aqui está minha resposta.',
        ]);
    }

    public function test_only_gestor_or_admin_can_pin_a_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'is_pinned' => false]);

        $this->actingAs($student)->post(route('forum.pin', [$course, $topic]))->assertForbidden();

        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $this->actingAs($gestor)->post(route('forum.pin', [$course, $topic]))->assertRedirect();

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => true]);
    }

    public function test_the_topic_author_can_edit_their_own_post_at_any_time_and_it_writes_an_edit_history_row(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'content' => 'Conteúdo original.']);

        $this->actingAs($student)->put(route('forum.update', [$course, $topic]), [
            'title' => $topic->title,
            'content' => 'Conteúdo editado.',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'content' => 'Conteúdo editado.']);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'previous_content' => 'Conteúdo original.',
        ]);
    }

    public function test_author_can_delete_their_own_topic_but_another_aluno_cannot(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $course->org_id]);

        $this->actingAs($otherStudent)->delete(route('forum.destroy', [$course, $topic]))->assertForbidden();
        $this->actingAs($author)->delete(route('forum.destroy', [$course, $topic]))->assertRedirect();

        $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
    }

    public function test_gestor_or_admin_can_delete_any_topic_directly_without_a_report(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)->delete(route('forum.destroy', [$course, $topic]))->assertRedirect();

        $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
    }

    public function test_an_enrolled_aluno_can_view_the_standalone_create_topic_page(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)->get(route('forum.create', $course))->assertOk();
    }

    public function test_author_can_delete_their_own_reply_but_another_aluno_cannot(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $course->org_id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($author)->create();

        $this->actingAs($otherStudent)->delete(route('forum-replies.destroy', [$course, $topic, $reply]))->assertForbidden();
        $this->actingAs($author)->delete(route('forum-replies.destroy', [$course, $topic, $reply]))->assertRedirect();

        $this->assertSoftDeleted('forum_replies', ['id' => $reply->id]);
    }

    public function test_an_admin_can_view_the_forum_with_pin_and_moderate_permissions(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->actingAs($admin)->get(route('forum.index', $course))->assertOk();
        $this->actingAs($admin)->post(route('forum.pin', [$course, $topic]))->assertRedirect();

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => true]);
    }

    public function test_fetch_new_replies_only_returns_replies_newer_than_since_id(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        $old = ForumReply::factory()->for($topic, 'topic')->for($student)->create();
        $new = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $response = $this->actingAs($student)->getJson(
            route('forum-replies.fetch', [$course, $topic]).'?since_id='.$old->id
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($old->id, $ids);
        $this->assertContains($new->id, $ids);
    }
}
