<?php

namespace Tests\Unit\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ForumReplyPolicy;
use Tests\TestCase;

/**
 * `ForumReply` is cascade-inherited (no `OrgScope`,
 * mirrors `QuizPolicy::parentCourse()`'s cascade pattern), so the parent
 * `Course`/tenancy check is resolved via `reply->topic->course`.
 */
class ForumReplyPolicyTest extends TestCase
{
    private function replyIn(Organization $org, ?User $author = null): ForumReply
    {
        $course = Course::factory()->inOrg($org->id)->create();
        $topicAuthor = User::factory()->inOrg($org->id)->create();
        $topic = ForumTopic::factory()->for($course)->for($topicAuthor)->inOrg($org->id)->create();
        $author ??= User::factory()->inOrg($org->id)->create();

        return ForumReply::factory()->for($topic, 'topic')->for($author)->create();
    }

    public function test_enrolled_aluno_can_view_the_reply_and_create_a_new_one(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $topicAuthor = User::factory()->inOrg($org->id)->create();
        $topic = ForumTopic::factory()->for($course)->for($topicAuthor)->inOrg($org->id)->create();

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['enrolled_at' => now(), 'status' => 'active']);

        $reply = ForumReply::factory()->for($topic, 'topic')->for($aluno)->create();

        $policy = new ForumReplyPolicy;
        $this->assertTrue($policy->view($aluno, $reply));
        $this->assertTrue($policy->create($aluno, $topic));
    }

    public function test_non_enrolled_aluno_cannot_view_or_create_a_reply(): void
    {
        $org = Organization::factory()->create();
        $reply = $this->replyIn($org);

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $policy = new ForumReplyPolicy;
        $this->assertFalse($policy->view($aluno, $reply));
        $this->assertFalse($policy->create($aluno, $reply->topic));
    }

    public function test_author_can_update_and_delete_their_own_reply_any_time(): void
    {
        $org = Organization::factory()->create();
        /** @var User $author */
        $author = User::factory()->inOrg($org->id)->create();
        $author->assignRole(RolesEnum::ALUNO->value);
        $reply = $this->replyIn($org, $author);

        $policy = new ForumReplyPolicy;
        $this->assertTrue($policy->update($author, $reply));
        $this->assertTrue($policy->delete($author, $reply));
    }

    public function test_another_aluno_cannot_update_or_delete_someone_elses_reply(): void
    {
        $org = Organization::factory()->create();
        $reply = $this->replyIn($org);

        /** @var User $otherAluno */
        $otherAluno = User::factory()->inOrg($org->id)->create();
        $otherAluno->assignRole(RolesEnum::ALUNO->value);

        $policy = new ForumReplyPolicy;
        $this->assertFalse($policy->update($otherAluno, $reply));
        $this->assertFalse($policy->delete($otherAluno, $reply));
    }

    public function test_gestor_of_the_same_org_can_update_and_delete_any_reply(): void
    {
        $org = Organization::factory()->create();
        $reply = $this->replyIn($org);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->withOrgContext($org);

        $policy = new ForumReplyPolicy;
        $this->assertTrue($policy->update($gestor, $reply));
        $this->assertTrue($policy->delete($gestor, $reply));
    }

    public function test_gestor_of_another_org_cannot_view_or_modify_the_reply(): void
    {
        $org = Organization::factory()->create();
        $reply = $this->replyIn($org);

        /** @var User $otherGestor */
        $otherGestor = User::factory()->inOrg(Organization::factory()->create()->id)->create();
        $otherGestor->assignRole(RolesEnum::GESTOR->value);

        $policy = new ForumReplyPolicy;
        $this->assertFalse($policy->view($otherGestor, $reply));
        $this->assertFalse($policy->update($otherGestor, $reply));
        $this->assertFalse($policy->delete($otherGestor, $reply));
    }

    public function test_admin_can_do_everything_regardless_of_org(): void
    {
        $reply = $this->replyIn(Organization::factory()->create());

        /** @var User $admin */
        $admin = User::factory()->inOrg(null)->create();
        $admin->assignRole(RolesEnum::ADMIN->value);

        $policy = new ForumReplyPolicy;
        $this->assertTrue($policy->view($admin, $reply));
        $this->assertTrue($policy->update($admin, $reply));
        $this->assertTrue($policy->delete($admin, $reply));
        $this->assertTrue($policy->pin($admin, $reply));
    }

    public function test_assigned_professor_can_create_reply_and_pin_it(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $topicAuthor = User::factory()->inOrg($org->id)->create();
        $topic = ForumTopic::factory()->for($course)->for($topicAuthor)->inOrg($org->id)->create();
        $reply = ForumReply::factory()->for($topic, 'topic')->for($topicAuthor)->create();

        /** @var User $professor */
        $professor = User::factory()->professor()->inOrg($org->id)->create();
        $course->professors()->attach($professor->id);

        /** @var User $outsider */
        $outsider = User::factory()->professor()->inOrg($org->id)->create();

        $policy = new ForumReplyPolicy;
        $this->assertTrue($policy->create($professor, $topic));
        $this->assertTrue($policy->pin($professor, $reply));

        $this->assertFalse($policy->create($outsider, $topic));
        $this->assertFalse($policy->pin($outsider, $reply));
    }

    public function test_aluno_cannot_pin_reply_even_their_own(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $topicAuthor = User::factory()->inOrg($org->id)->create();
        $topic = ForumTopic::factory()->for($course)->for($topicAuthor)->inOrg($org->id)->create();

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['enrolled_at' => now(), 'status' => 'active']);

        $reply = ForumReply::factory()->for($topic, 'topic')->for($aluno)->create();

        $policy = new ForumReplyPolicy;
        $this->assertFalse($policy->pin($aluno, $reply));
    }

    public function test_gestor_of_same_org_can_pin_reply_but_other_org_cannot(): void
    {
        $org = Organization::factory()->create();
        $reply = $this->replyIn($org);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        /** @var User $otherGestor */
        $otherGestor = User::factory()->inOrg(Organization::factory()->create()->id)->create();
        $otherGestor->assignRole(RolesEnum::GESTOR->value);

        $otherOrg = $otherGestor->credentials()->first()->organization;

        $policy = new ForumReplyPolicy;

        // Gestor resolve a org do curso pelo contexto do host
        $this->withOrgContext($org);
        $this->assertTrue($policy->pin($gestor, $reply));

        $this->withOrgContext($otherOrg);
        $this->assertFalse($policy->pin($otherGestor, $reply));
    }
}
