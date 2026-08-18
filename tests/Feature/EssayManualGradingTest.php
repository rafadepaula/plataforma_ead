<?php

namespace Tests\Feature;

use App\Actions\GradeEssayAnswerAction;
use App\Actions\SubmitQuizAttemptAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * `GradeEssayAnswerAction`'s manual-grading write path and
 * its `finalizeGrading()` handoff (delegated to
 * `SubmitQuizAttemptAction::finalizeGrading()`), plus `QuizAttemptPolicy`
 * org-scoping of the Gestor grading screen.
 */
class EssayManualGradingTest extends TestCase
{
    private function attemptAwaitingGrading(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Resposta dissertativa do aluno.'],
        ]);

        return [$attempt, $essayQuestion, $org, $lesson, $aluno];
    }

    private function gestorFor(Organization $org): User
    {
        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return $gestor;
    }

    public function test_grading_the_only_pending_essay_answer_finalizes_the_attempt(): void
    {
        [$attempt, $essayQuestion, $org, $lesson, $aluno] = $this->attemptAwaitingGrading();
        $gestor = $this->gestorFor($org);

        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $graded = app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => $essayAnswer->id, 'is_correct' => true],
        ]);

        $this->assertSame('graded', $graded->status);
        // 2 questions total, both correct (1 auto-graded + 1 essay) = 100%.
        $this->assertEquals(100.0, (float) $graded->score_percentage);
        $this->assertTrue($graded->is_passed);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => true,
            'graded_by' => $gestor->id,
        ]);
        $this->assertNotNull($graded->answers()->find($essayAnswer->id)->graded_at);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'quiz_passed',
        ]);
    }

    public function test_grading_skips_an_answer_id_that_does_not_belong_to_the_attempt(): void
    {
        [$attempt, $essayQuestion, $org] = $this->attemptAwaitingGrading();
        $gestor = $this->gestorFor($org);

        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $graded = app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => 999999, 'is_correct' => true],
        ]);

        // The bogus answer_id is silently skipped; the real essay answer
        // remains ungraded, so the attempt is not finalized.
        $this->assertSame('awaiting_manual_grading', $graded->status);
        $this->assertNull($graded->answers()->find($essayAnswer->id)->graded_at);
    }

    public function test_finalization_only_happens_once_every_essay_answer_of_the_attempt_is_graded(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();

        $essayOne = QuizQuestion::factory()->for($quiz)->essay()->create();
        $essayTwo = QuizQuestion::factory()->for($quiz)->essay()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $essayOne->id, 'essay_answer' => 'Primeira resposta.'],
            ['question_id' => $essayTwo->id, 'essay_answer' => 'Segunda resposta.'],
        ]);

        $gestor = $this->gestorFor($org);
        $answerOne = $attempt->answers()->where('question_id', $essayOne->id)->firstOrFail();
        $answerTwo = $attempt->answers()->where('question_id', $essayTwo->id)->firstOrFail();

        $stillPending = app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => $answerOne->id, 'is_correct' => true],
        ]);

        $this->assertSame('awaiting_manual_grading', $stillPending->status);
        $this->assertNull($stillPending->score_percentage);

        $finalized = app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => $answerTwo->id, 'is_correct' => false],
        ]);

        $this->assertSame('graded', $finalized->status);
        $this->assertEquals(50.0, (float) $finalized->score_percentage);
    }

    public function test_finalize_grading_uses_the_same_score_formula_as_auto_grading_including_unanswered_questions_as_wrong(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();
        // A 3rd question the student leaves entirely unanswered — it must
        // still count in the denominator, scored as wrong.
        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Resposta.'],
        ]);

        $gestor = $this->gestorFor($org);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $graded = app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => $essayAnswer->id, 'is_correct' => true],
        ]);

        // 3 total questions, 2 correct (1 auto + 1 essay), 1 unanswered
        // counted as wrong -> 2/3 = 66.67%.
        $this->assertEqualsWithDelta(66.67, (float) $graded->score_percentage, 0.01);
    }

    public function test_a_gestor_from_a_different_org_cannot_grade_the_attempt(): void
    {
        [$attempt] = $this->attemptAwaitingGrading();
        $otherOrg = Organization::factory()->create();
        $otherGestor = $this->gestorFor($otherOrg);

        $this->assertFalse($otherGestor->can('grade', $attempt));
    }

    public function test_the_owning_orgs_gestor_can_grade_the_attempt(): void
    {
        [$attempt, , $org] = $this->attemptAwaitingGrading();
        $gestor = $this->gestorFor($org);

        $this->assertTrue($gestor->can('grade', $attempt));
    }

    public function test_an_admin_can_grade_any_orgs_attempt(): void
    {
        [$attempt] = $this->attemptAwaitingGrading();

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertTrue($admin->can('grade', $attempt));
    }
}
