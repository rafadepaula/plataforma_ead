<?php

namespace Tests\Feature;

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
 * HTTP-layer coverage for the Gestor's essay-grading queue+screen
 * (`EssayGradingController::pending/show/grade`): FIFO ordering of the
 * pending queue, role/org-scope enforcement via `QuizAttemptPolicy`, and
 * the full-grade submission's status/score transition. Business-rule
 * coverage for `GradeEssayAnswerAction`/`finalizeGrading()` itself lives
 * in `EssayManualGradingTest` — this file is additive, focused on the
 * queue ordering and the HTTP request/response contract.
 */
class EssayGradingTest extends TestCase
{
    private function makeCourseWithEssayQuiz(?Organization $org = null): array
    {
        $org ??= Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        return [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion];
    }

    private function enrolledAluno(Course $course): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return $aluno;
    }

    private function gestorFor(Organization $org): User
    {
        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return $gestor;
    }

    private function submitAttempt(Lesson $lesson, User $aluno, QuizQuestion $choiceQuestion, QuizOption $correct, QuizQuestion $essayQuestion, string $essayAnswer = 'Resposta dissertativa.')
    {
        return app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => $essayAnswer],
        ]);
    }

    public function test_pending_queue_orders_attempts_fifo_by_completed_at(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();

        $alunoOne = $this->enrolledAluno($course);
        $alunoTwo = $this->enrolledAluno($course);
        $alunoThree = $this->enrolledAluno($course);

        // Submit out of chronological order, then force distinct
        // `completed_at` timestamps so FIFO ordering is unambiguous.
        $attemptSecond = $this->submitAttempt($lesson, $alunoTwo, $choiceQuestion, $correct, $essayQuestion, 'Segunda a chegar.');
        $attemptSecond->update(['completed_at' => now()->subMinutes(5)]);

        $attemptFirst = $this->submitAttempt($lesson, $alunoOne, $choiceQuestion, $correct, $essayQuestion, 'Primeira a chegar.');
        $attemptFirst->update(['completed_at' => now()->subMinutes(10)]);

        $attemptThird = $this->submitAttempt($lesson, $alunoThree, $choiceQuestion, $correct, $essayQuestion, 'Terceira a chegar.');
        $attemptThird->update(['completed_at' => now()->subMinutes(1)]);

        $gestor = $this->gestorFor($org);

        $response = $this->actingAs($gestor)->get(route('quiz-attempts.pending'));

        $response->assertOk();
        $attempts = $response->viewData('attempts');
        $orderedIds = collect($attempts->items())->pluck('id')->all();

        $this->assertSame(
            [$attemptFirst->id, $attemptSecond->id, $attemptThird->id],
            $orderedIds,
        );
    }

    public function test_admin_can_view_pending_queue(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->get(route('quiz-attempts.pending'));

        $response->assertOk();
    }

    public function test_aluno_cannot_view_pending_queue(): void
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($aluno)->get(route('quiz-attempts.pending'));

        $response->assertForbidden();
    }

    public function test_gestor_from_another_org_gets_403_viewing_the_attempt(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);

        $otherOrg = Organization::factory()->create();
        $otherGestor = $this->gestorFor($otherOrg);

        $response = $this->actingAs($otherGestor)->get(route('quiz-attempts.show', $attempt));

        $response->assertForbidden();
    }

    public function test_gestor_from_another_org_gets_403_grading_the_attempt(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $otherOrg = Organization::factory()->create();
        $otherGestor = $this->gestorFor($otherOrg);

        $response = $this->actingAs($otherGestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => null,
        ]);
    }

    public function test_owning_orgs_gestor_can_submit_a_full_grade_and_attempt_transitions_to_graded(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $gestor = $this->gestorFor($org);

        $response = $this->actingAs($gestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ]);

        $response->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        // 2 questions, both correct (1 auto + 1 essay) = 100%.
        $this->assertEquals(100.0, (float) $attempt->score_percentage);
        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => true,
            'graded_by' => $gestor->id,
        ]);
    }

    public function test_admin_can_grade_any_orgs_attempt(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $response = $this->actingAs($admin)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => false],
            ],
        ]);

        $response->assertRedirect(route('quiz-attempts.pending'));
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
    }

    public function test_incomplete_grades_payload_never_finalizes_the_attempt_even_when_bypassing_client_side_required(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();

        // A second essay question — the raw HTTP request below will omit
        // its verdict entirely, simulating a client that bypassed the
        // `required` radio-group attribute.
        $essayQuestionTwo = QuizQuestion::factory()->for($quiz)->essay()->create();

        $aluno = $this->enrolledAluno($course);
        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Resposta um.'],
            ['question_id' => $essayQuestionTwo->id, 'essay_answer' => 'Resposta dois.'],
        ]);

        $essayAnswerOne = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();
        $essayAnswerTwo = $attempt->answers()->where('question_id', $essayQuestionTwo->id)->firstOrFail();

        $gestor = $this->gestorFor($org);

        // Only grades the first essay answer, leaving the second
        // ungraded — the server must never silently finalize/mark the
        // attempt `graded` with a null-verdict answer still present.
        $response = $this->actingAs($gestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswerOne->id, 'is_correct' => true],
            ],
        ]);

        $response->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('awaiting_manual_grading', $attempt->status);
        $this->assertNull($attempt->score_percentage);
        $this->assertNull($attempt->is_passed);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswerOne->id,
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswerTwo->id,
            'is_correct' => null,
            'graded_at' => null,
        ]);
    }

    public function test_grade_request_requires_at_least_one_grade(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);

        $gestor = $this->gestorFor($org);

        $response = $this->actingAs($gestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [],
        ]);

        $response->assertSessionHasErrors('grades');

        $attempt->refresh();
        $this->assertSame('awaiting_manual_grading', $attempt->status);
    }

    public function test_essay_answer_is_html_escaped_on_the_grading_screen(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $maliciousAnswer = '<script>alert(1)</script>';
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion, $maliciousAnswer);

        $gestor = $this->gestorFor($org);

        $response = $this->actingAs($gestor)->get(route('quiz-attempts.show', $attempt));

        $response->assertOk();
        $response->assertDontSee($maliciousAnswer, false);
        $response->assertSee(e($maliciousAnswer), false);
    }

    public function test_grading_screen_and_endpoint_remain_reachable_and_repostable_after_the_attempt_is_graded(): void
    {
        [$org, $course, $lesson, $quiz, $choiceQuestion, $correct, $essayQuestion] = $this->makeCourseWithEssayQuiz();
        $aluno = $this->enrolledAluno($course);
        $attempt = $this->submitAttempt($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $gestor = $this->gestorFor($org);

        $this->actingAs($gestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ])->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $firstScore = $attempt->score_percentage;

        // Documented current behaviour (no read-only guard exists yet):
        // the show screen for an already-graded attempt stays reachable,
        // and re-posting a verdict re-runs finalizeGrading(), recomputing
        // score/is_passed on an attempt that may already be certificated.
        $this->actingAs($gestor)->get(route('quiz-attempts.show', $attempt))->assertOk();

        $response = $this->actingAs($gestor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => false],
            ],
        ]);

        $response->assertRedirect(route('quiz-attempts.pending'));
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertNotEquals($firstScore, $attempt->score_percentage);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => false,
        ]);
    }
}
