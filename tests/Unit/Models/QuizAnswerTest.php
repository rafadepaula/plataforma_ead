<?php

namespace Tests\Unit\Models;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-08 §2.1 — `QuizAnswer` is cascade-inherited (no `OrgScope`, see
 * `quizzes-architecture` skill); this covers its relationships directly.
 */
class QuizAnswerTest extends TestCase
{
    public function test_it_has_a_graded_by_relationship(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $lesson = Lesson::factory()->for(Module::factory()->for($course))->create(['type' => 'quiz']);
        $quiz = Quiz::factory()->for($lesson)->create();
        $question = QuizQuestion::factory()->for($quiz)->essay()->create();
        $aluno = User::factory()->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create();

        /** @var User $gestor */
        $gestor = User::factory()->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $answer = QuizAnswer::factory()
            ->for($attempt, 'attempt')
            ->for($question, 'question')
            ->essay('Minha resposta.')
            ->create(['graded_by' => $gestor->id]);

        $this->assertTrue($answer->gradedBy->is($gestor));
    }
}
