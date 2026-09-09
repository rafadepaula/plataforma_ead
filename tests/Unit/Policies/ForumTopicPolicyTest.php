<?php

namespace Tests\Unit\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ForumTopicPolicy;
use Tests\TestCase;

/**
 * `ForumTopic` is directly `OrgScope`d, but the Policy
 * still must gate `view`/`create` to an enrolled Aluno  or a
 * same-org Gestor/Admin, and reserve `update`/`delete` to the author or a
 * same-org Gestor/Admin (author has no time-limit), and `pin` to
 * Gestor/Admin only.
 */
class ForumTopicPolicyTest extends TestCase
{
    private function topicIn(Organization $org, ?User $author = null): ForumTopic
    {
        $course = Course::factory()->inOrg($org->id)->create();
        $author ??= User::factory()->inOrg($org->id)->create();

        return ForumTopic::factory()->for($course)->for($author)->inOrg($org->id)->create();
    }

    public function test_enrolled_aluno_can_view_and_create_topics_for_the_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['enrolled_at' => now(), 'status' => 'active']);

        $topic = ForumTopic::factory()->for($course)->for($aluno)->inOrg($org->id)->create();

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->view($aluno, $topic));
        $this->assertTrue($policy->create($aluno, $course));
    }

    public function test_non_enrolled_aluno_cannot_view_or_create_topics(): void
    {
        $org = Organization::factory()->create();
        $topic = $this->topicIn($org);

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $policy = new ForumTopicPolicy;
        $this->assertFalse($policy->view($aluno, $topic));
        $this->assertFalse($policy->create($aluno, $topic->course));
    }

    public function test_assigned_professor_can_create_topic_for_course_and_unassigned_cannot(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        /** @var User $professor */
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->attach($professor->id);

        /** @var User $outsider */
        $outsider = User::factory()->professor()->create(['org_id' => $org->id]);

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->create($professor, $course));
        $this->assertFalse($policy->create($outsider, $course));
    }

    public function test_gestor_of_another_org_cannot_view_the_topic(): void
    {
        $org = Organization::factory()->create();
        $topic = $this->topicIn($org);

        /** @var User $otherGestor */
        $otherGestor = User::factory()->inOrg(Organization::factory()->create()->id)->create();
        $otherGestor->assignRole(RolesEnum::GESTOR->value);

        $this->assertFalse((new ForumTopicPolicy)->view($otherGestor, $topic));
    }

    public function test_author_can_update_and_delete_their_own_topic_any_time(): void
    {
        $org = Organization::factory()->create();
        /** @var User $author */
        $author = User::factory()->inOrg($org->id)->create();
        $author->assignRole(RolesEnum::ALUNO->value);
        $topic = $this->topicIn($org, $author);

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->update($author, $topic));
        $this->assertTrue($policy->delete($author, $topic));
    }

    public function test_another_aluno_cannot_update_or_delete_someone_elses_topic(): void
    {
        $org = Organization::factory()->create();
        $topic = $this->topicIn($org);

        /** @var User $otherAluno */
        $otherAluno = User::factory()->inOrg($org->id)->create();
        $otherAluno->assignRole(RolesEnum::ALUNO->value);

        $policy = new ForumTopicPolicy;
        $this->assertFalse($policy->update($otherAluno, $topic));
        $this->assertFalse($policy->delete($otherAluno, $topic));
    }

    public function test_gestor_of_the_same_org_can_update_and_delete_any_topic(): void
    {
        $org = Organization::factory()->create();
        $topic = $this->topicIn($org);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->withOrgContext($org);

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->update($gestor, $topic));
        $this->assertTrue($policy->delete($gestor, $topic));
    }

    public function test_only_gestor_or_admin_can_pin_a_topic(): void
    {
        $org = Organization::factory()->create();
        $topic = $this->topicIn($org);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->withOrgContext($org);

        /** @var User $author */
        $author = $topic->user;

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->pin($gestor, $topic));
        $this->assertFalse($policy->pin($author, $topic));
    }

    public function test_admin_can_do_everything_regardless_of_org(): void
    {
        $topic = $this->topicIn(Organization::factory()->create());

        /** @var User $admin */
        $admin = User::factory()->inOrg(null)->create();
        $admin->assignRole(RolesEnum::ADMIN->value);

        $policy = new ForumTopicPolicy;
        $this->assertTrue($policy->view($admin, $topic));
        $this->assertTrue($policy->update($admin, $topic));
        $this->assertTrue($policy->delete($admin, $topic));
        $this->assertTrue($policy->pin($admin, $topic));
    }
}
