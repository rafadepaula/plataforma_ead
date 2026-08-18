<?php

namespace Tests\Unit\Policies;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Policies\QuizAttemptPolicy;
use Tests\TestCase;

/**
 * `QuizAttemptPolicy` gates the Gestor's manual essay
 * -grading screen. The "not admin/gestor" branch is already blocked at
 * the `role:admin|gestor` route-middleware layer for every controller
 * that reaches this Policy (see `routes/web.php`), so it is exercised
 * directly here (same defense-in-depth shape as `ModulePolicy`/
 * `LessonPolicy`).
 */
class QuizAttemptPolicyTest extends TestCase
{
    private function attempt(Organization $org): QuizAttempt
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = Quiz::factory()->for($lesson)->create();

        /** @var User $aluno */
        $aluno = User::factory()->create();

        return QuizAttempt::factory()->for($quiz)->for($aluno)->create();
    }

    public function test_aluno_cannot_view_or_grade_an_attempt(): void
    {
        $attempt = $this->attempt(Organization::factory()->create());

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $policy = new QuizAttemptPolicy;
        $this->assertFalse($policy->view($aluno, $attempt));
        $this->assertFalse($policy->grade($aluno, $attempt));
    }
}
