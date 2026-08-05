<?php

namespace Tests\Unit\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\User;
use App\Policies\QuizPolicy;
use Tests\TestCase;

/**
 * SPEC-08 §1–§2.1 — `QuizPolicy` cascades `lesson -> module -> course.
 * org_id` (mirrors `ModulePolicy`/`LessonPolicy` one level further down).
 * `view()` has no dedicated HTTP route wired to it yet (question authoring
 * happens on `quizzes.edit`), so it is exercised directly here.
 */
class QuizPolicyTest extends TestCase
{
    private function quiz(Organization $org): Quiz
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);

        return Quiz::factory()->for($lesson)->create();
    }

    public function test_gestor_of_the_same_org_can_view_the_quiz(): void
    {
        $org = Organization::factory()->create();
        $quiz = $this->quiz($org);

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->assertTrue((new QuizPolicy)->view($gestor, $quiz));
    }

    public function test_gestor_of_another_org_cannot_view_the_quiz(): void
    {
        $org = Organization::factory()->create();
        $quiz = $this->quiz($org);

        /** @var User $otherGestor */
        $otherGestor = User::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $otherGestor->assignRole(RolesEnum::GESTOR->value);

        $this->assertFalse((new QuizPolicy)->view($otherGestor, $quiz));
    }

    public function test_aluno_cannot_view_the_quiz(): void
    {
        $org = Organization::factory()->create();
        $quiz = $this->quiz($org);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->assertFalse((new QuizPolicy)->view($aluno, $quiz));
    }

    public function test_admin_can_view_any_orgs_quiz(): void
    {
        $quiz = $this->quiz(Organization::factory()->create());

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertTrue((new QuizPolicy)->view($admin, $quiz));
    }
}
