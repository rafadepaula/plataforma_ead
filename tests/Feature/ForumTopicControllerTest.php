<?php

namespace Tests\Feature;

use App\Actions\DeleteForumPostAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ForumTopicPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * `ForumTopicController`'s HTTP contract: what the
 * topic list hands the view (`canCreateTopic`/`canPin`, 15-per-page
 * pagination, pinned-first ordering), topic creation for a multi-org
 * Aluno, what the thread view hands the polling container
 * (`lastReplyId`), and who may toggle the pin.
 */
class ForumTopicControllerTest extends TestCase
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

    private function topicFor(Course $course, User $author, array $attributes = []): ForumTopic
    {
        return ForumTopic::factory()->for($course)->for($author)->create(
            array_merge(['org_id' => $course->org_id], $attributes)
        );
    }

    public function test_index_lets_an_enrolled_aluno_open_a_topic_but_not_pin_one(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $response->assertViewIs('forum.index');
        $response->assertViewHas('canCreateTopic', true);
        $response->assertViewHas('canPin', false);
    }

    public function test_index_grants_the_pin_control_to_a_same_org_gestor(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);
        $gestor = $this->actingAsOrgUser($organization, RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('forum.index', $course));

        $response->assertOk();
        $response->assertViewHas('canCreateTopic', true);
        $response->assertViewHas('canPin', true);
    }

    public function test_index_grants_the_pin_control_to_an_admin_of_any_org(): void
    {
        $course = $this->publishedCourse();

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('forum.index', $course));

        $response->assertOk();
        $response->assertViewHas('canPin', true);
    }

    public function test_index_paginates_the_topic_list_at_fifteen_per_page(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        ForumTopic::factory()->count(20)->for($course)->for($student)->create(['org_id' => $course->org_id]);

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $topics = $response->viewData('topics');

        $this->assertSame(15, $topics->perPage());
        $this->assertCount(15, $topics->items());
        $this->assertSame(20, $topics->total());
    }

    public function test_index_lists_a_pinned_topic_ahead_of_more_recent_unpinned_ones(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $recent = $this->topicFor($course, $student, ['created_at' => now()->subHour()]);
        $pinned = $this->topicFor($course, $student, ['is_pinned' => true, 'created_at' => now()->subWeek()]);

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $ids = collect($response->viewData('topics')->items())->pluck('id')->all();

        $this->assertSame([$pinned->id, $recent->id], $ids);
    }

    public function test_a_non_enrolled_aluno_is_redirected_away_from_the_topic_list(): void
    {
        $course = $this->publishedCourse();

        /** @var User $outsider */
        $outsider = User::factory()->create(['org_id' => null]);
        $outsider->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($outsider)
            ->get(route('forum.index', $course))
            ->assertRedirect(route('student.courses.index'));
    }

    public function test_a_gestor_from_another_org_cannot_reach_the_topic_list(): void
    {
        $course = $this->publishedCourse();
        $foreignGestor = $this->actingAsOrgUser(Organization::factory()->create(), RolesEnum::GESTOR->value);

        $this->actingAs($foreignGestor)
            ->get(route('forum.index', $course))
            ->assertForbidden();
    }

    public function test_create_renders_the_new_topic_form_for_an_enrolled_aluno(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $response = $this->actingAs($student)->get(route('forum.create', $course));

        $response->assertOk();
        $response->assertViewIs('forum.create');
        $response->assertViewHas('course');
    }

    public function test_store_creates_the_topic_and_redirects_to_the_new_thread(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $response = $this->actingAs($student)
            ->post(route('forum.store', $course), [
                'title' => 'Como interpretar o gráfico da aula 3?',
                'content' => 'Não consegui entender o eixo vertical.',
            ]);

        $topic = ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();

        $response
            ->assertRedirect(route('forum.show', [$course->id, $topic->id]))
            ->assertSessionHas('success', 'Tópico criado com sucesso.');

        $this->assertSame('Como interpretar o gráfico da aula 3?', $topic->title);
        $this->assertSame($student->id, $topic->user_id);
    }

    public function test_store_rejects_a_topic_without_a_title(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->post(route('forum.store', $course), ['title' => '', 'content' => 'Só o corpo.'])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('forum_topics', 0);
    }

    public function test_store_strips_html_from_the_topic_content_before_persisting_it(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)
            ->post(route('forum.store', $course), [
                'title' => 'Tópico com marcação',
                'content' => '<b>Negrito</b> e <script>alert(1)</script>',
            ])
            ->assertRedirect();

        $topic = ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();

        $this->assertSame('Negrito e alert(1)', $topic->content);
    }

    /**
     * A multi-org Aluno carries `org_id === null` and has no
     * `active_org_id` in session, so `OrgScope`'s `creating` hook cannot
     * resolve a tenant and would throw `UnresolvedOrgContextException`.
     * The topic must still be persisted under the Course's own Org.
     */
    public function test_a_multi_org_aluno_can_create_a_topic_and_it_inherits_the_courses_org(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);
        $student = $this->enrolledStudent($course);

        $this->assertNull($student->org_id);
        $this->assertNull(session('active_org_id'));

        $this->actingAs($student)
            ->post(route('forum.store', $course), ['title' => 'Primeiro tópico', 'content' => 'Conteúdo.'])
            ->assertRedirect();

        $topic = ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();

        $this->assertSame($organization->id, $topic->org_id);
    }

    public function test_edit_renders_the_form_for_the_author_and_is_refused_to_another_aluno(): void
    {
        $course = $this->publishedCourse();
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $author);

        $response = $this->actingAs($author)->get(route('forum.edit', [$course, $topic]));

        $response->assertOk();
        $response->assertViewIs('forum.edit');
        $response->assertViewHas('topic');
        $response->assertViewHas('course');

        $this->actingAs($otherStudent)
            ->get(route('forum.edit', [$course, $topic]))
            ->assertForbidden();
    }

    public function test_show_hands_the_polling_container_the_highest_reply_id(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        ForumReply::factory()->for($topic, 'topic')->for($student)->create();
        $latest = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();
        $response->assertViewIs('forum.show');
        $response->assertViewHas('lastReplyId', $latest->id);
    }

    public function test_show_reports_a_zero_last_reply_id_on_a_thread_with_no_replies(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $this->actingAs($student)
            ->get(route('forum.show', [$course, $topic]))
            ->assertOk()
            ->assertViewHas('lastReplyId', 0);
    }

    public function test_pin_toggles_the_topic_in_both_directions_for_a_gestor(): void
    {
        $organization = Organization::factory()->create();
        $course = $this->publishedCourse($organization);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['is_pinned' => false]);
        $gestor = $this->actingAsOrgUser($organization, RolesEnum::GESTOR->value);

        $this->actingAs($gestor)->post(route('forum.pin', [$course, $topic]))->assertRedirect();
        $this->assertTrue((bool) $topic->fresh()->is_pinned);

        $this->actingAs($gestor)->post(route('forum.pin', [$course, $topic]))->assertRedirect();
        $this->assertFalse((bool) $topic->fresh()->is_pinned);
    }

    public function test_pin_is_refused_to_a_plain_aluno(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['is_pinned' => false]);

        $this->actingAs($student)->post(route('forum.pin', [$course, $topic]))->assertForbidden();

        $this->assertFalse((bool) $topic->fresh()->is_pinned);
    }

    public function test_pin_is_refused_to_a_gestor_from_another_org(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['is_pinned' => false]);
        $foreignGestor = $this->actingAsOrgUser(Organization::factory()->create(), RolesEnum::GESTOR->value);

        $this->actingAs($foreignGestor)->post(route('forum.pin', [$course, $topic]))->assertForbidden();

        $this->assertFalse((bool) $topic->fresh()->is_pinned);
    }

    /**
     * A topic removed by moderation is soft-deleted
     * ({@see DeleteForumPostAction}), so it must disappear
     * from the list. `ForumTopicController` bypasses `OrgScope` on every
     * lookup; that bypass must stay surgical and never take
     * `SoftDeletingScope` with it.
     */
    public function test_index_omits_a_topic_that_was_removed_by_moderation(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $visible = $this->topicFor($course, $student);
        $removed = $this->topicFor($course, $student);
        $removed->delete();

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $ids = collect($response->viewData('topics')->items())->pluck('id')->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_show_reports_a_removed_topic_as_missing_instead_of_rendering_it(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $topic->delete();

        $this->actingAs($student)
            ->get(route('forum.show', [$course, $topic]))
            ->assertNotFound();
    }

    public function test_a_removed_topic_can_no_longer_be_edited_pinned_or_replied_to(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $gestor = $this->actingAsOrgUser(Course::query()->withoutGlobalScopes()->find($course->id)->organization, RolesEnum::GESTOR->value);
        $topic = $this->topicFor($course, $student);
        $topic->delete();

        $this->actingAs($student)
            ->get(route('forum.edit', [$course, $topic]))
            ->assertNotFound();

        $this->actingAs($student)
            ->post(route('forum-replies.store', [$course, $topic]), ['content' => 'Resposta em tópico removido.'])
            ->assertNotFound();

        $this->actingAs($gestor)
            ->post(route('forum.pin', [$course, $topic]))
            ->assertNotFound();

        $this->assertDatabaseMissing('forum_replies', ['topic_id' => $topic->id]);
    }

    /**
     * `pinnedFirst()` sorts globally, before pagination — so every pinned
     * topic lands on page 1 and page 2 carries unpinned rows only. This
     * is the intended hierarchy, locked here so a later switch to
     * per-page sorting is caught.
     */
    public function test_pinned_topics_all_land_on_the_first_page_leaving_page_two_unpinned(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $this->topicFor($course, $student, ['is_pinned' => true, 'created_at' => now()->subYear()]);

        for ($index = 0; $index < 20; $index++) {
            $this->topicFor($course, $student, ['created_at' => now()->subMinutes($index)]);
        }

        $firstPage = $this->actingAs($student)->get(route('forum.index', $course));
        $firstPage->assertOk();
        $this->assertTrue(collect($firstPage->viewData('topics')->items())->first()->is_pinned);

        $secondPage = $this->actingAs($student)->get(route('forum.index', $course).'?page=2');
        $secondPage->assertOk();
        $secondPageTopics = collect($secondPage->viewData('topics')->items());

        $this->assertCount(6, $secondPageTopics);
        $this->assertTrue($secondPageTopics->every(fn (ForumTopic $topic): bool => ! $topic->is_pinned));
    }

    /**
     * With no topics and no posting rights the empty state must stand on
     * its own: no orphan action button inside it and no floating action
     * button behind it on mobile.
     *
     * `viewAny()` and `create()` are the same `hasCourseAccess()` check
     * today, so no real role can read the list without being able to post.
     * The policy is swapped for a double that denies only `create` and the
     * request still goes through the route, so the controller — not this
     * test — is what computes `canCreateTopic` for the view.
     */
    public function test_the_empty_state_offers_no_create_action_when_the_viewer_cannot_post(): void
    {
        $course = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        Gate::policy(ForumTopic::class, (new class extends ForumTopicPolicy
        {
            public function create(User $user, Course $course): bool
            {
                return false;
            }
        })::class);

        $response = $this->actingAs($student)->get(route('forum.index', $course));

        $response->assertOk();
        $response->assertViewIs('forum.index');
        $response->assertViewHas('canCreateTopic', false);
        $response->assertViewHas('canPin', false);

        $markup = $response->getContent();

        $this->assertStringContainsString('Nenhum tópico por aqui', $markup);
        $this->assertStringNotContainsString('empty-new-topic-button', $markup);
        $this->assertStringNotContainsString('new-topic-fab', $markup);
        $this->assertStringNotContainsString('new-topic-button', $markup);
        $this->assertStringNotContainsString('new-topic-form', $markup);
    }
}
