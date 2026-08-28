<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * the HTTP layer wiring Bucket 1's Actions/Policies/
 * Requests: Gestor CRUD of the 1:1 Lesson<->Quiz, nested QuizQuestion
 * CRUD + reorder, and the Gestor's manual essay-grading screen. Student
 * quiz-taking  is covered at the Action level by
 * `SubmitQuizAttemptActionTest`/`QuizAttemptLimitsTest` and at the browser
 * level by `tests/Browser/StudentQuizTakingDuskTest.php`.
 */
class QuizManagementTest extends TestCase
{
    private function quizLesson(Organization $org): Lesson
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        return Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
    }

    private function awaitingManualGradingAttempt(Organization $org, string $courseTitle): QuizAttempt
    {
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => $courseTitle]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $question = QuizQuestion::factory()->for($quiz)->essay()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()->create();
        QuizAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->essay('Minha resposta.')->create();

        return $attempt;
    }

    /**
     * the full HTTP round trip through every
     * controller this bucket wires up: Gestor authors a Quiz + a
     * single_choice/essay question pair, an enrolled Aluno submits it via
     * `student.quizzes.submit` (whose payload shape — `answers` keyed by
     * `question_id`, matching `student/quizzes/show.blade.php`'s form
     * fields — is folded back into `SubmitQuizAttemptAction`'s expected
     * `{question_id, ...}` list shape by the controller), then the Gestor
     * grades the pending essay answer via `quiz-attempts.grade`, finalizing
     * the attempt.
     */
    public function test_the_full_quiz_authoring_taking_and_grading_flow_works_end_to_end_over_http(): void
    {
        $org = Organization::factory()->create();
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);

        $this->post(route('quizzes.store', $lesson), [
            'title' => 'Prova Final',
            'min_score_percentage' => 70,
        ])->assertRedirect();
        $quiz = $lesson->fresh()->quiz;

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Quanto é 2 + 2?',
            'type' => 'single_choice',
            'options' => [
                ['option_text' => '4', 'is_correct' => '1'],
                ['option_text' => '5'],
            ],
        ])->assertRedirect();
        $question = $quiz->fresh()->questions()->firstOrFail();
        $correctOption = $question->options()->where('is_correct', true)->firstOrFail();

        $essayQuestion = $quiz->questions()->create([
            'question_text' => 'Disserte sobre o assunto.',
            'type' => 'essay',
            'order_index' => 1,
        ]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($lesson->module->course_id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $this->get(route('student.quizzes.show', $lesson))->assertOk();

        $this->post(route('student.quizzes.submit', $lesson), [
            'answers' => [
                $question->id => ['selected_option_ids' => [$correctOption->id]],
                $essayQuestion->id => ['essay_answer' => 'Minha resposta dissertativa.'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $attempt = $quiz->fresh()->attempts()->where('user_id', $aluno->id)->firstOrFail();
        $this->assertSame('awaiting_manual_grading', $attempt->status);

        $this->actingAs($gestor);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $this->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => '1'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->score_percentage);
        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'quiz_passed',
        ]);
    }

    // Quiz CRUD

    public function test_gestor_can_create_a_quiz_for_a_lesson_of_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);

        $this->get(route('quizzes.create', $lesson))
            ->assertOk()
            ->assertViewIs('quizzes.create');

        $response = $this->post(route('quizzes.store', $lesson), [
            'title' => 'Prova Final',
            'instructions' => 'Responda com atenção.',
            'allow_retries' => true,
            'min_score_percentage' => 70,
        ]);

        $quiz = Quiz::query()->where('lesson_id', $lesson->id)->firstOrFail();
        $response->assertRedirect(route('quizzes.edit', $quiz));
        $this->assertDatabaseHas('quizzes', ['lesson_id' => $lesson->id, 'title' => 'Prova Final']);
    }

    public function test_creating_a_second_quiz_for_a_lesson_that_already_has_one_redirects_with_an_error_instead_of_duplicating(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);
        $quiz = Quiz::factory()->for($lesson)->create();

        $this->get(route('quizzes.create', $lesson))
            ->assertRedirect(route('quizzes.edit', $quiz))
            ->assertSessionHas('error');

        $this->post(route('quizzes.store', $lesson), [
            'title' => 'Segunda Prova',
            'min_score_percentage' => 70,
        ])->assertRedirect(route('quizzes.edit', $quiz))
            ->assertSessionHas('error');

        $this->assertSame(1, Quiz::query()->where('lesson_id', $lesson->id)->count());
    }

    public function test_aluno_cannot_create_a_quiz(): void
    {
        $org = Organization::factory()->create();
        $lesson = $this->quizLesson($org);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->post(route('quizzes.store', $lesson), [
            'title' => 'Prova Proibida',
            'min_score_percentage' => 70,
        ])->assertForbidden();
    }

    public function test_gestor_cannot_manage_a_quiz_of_another_orgs_lesson(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherLesson = $this->quizLesson($otherOrg);
        $otherQuiz = Quiz::factory()->for($otherLesson)->create();
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->get(route('quizzes.edit', $otherQuiz))->assertForbidden();
        $this->put(route('quizzes.update', $otherQuiz), [
            'title' => 'Invasão',
            'min_score_percentage' => 70,
        ])->assertForbidden();
        $this->delete(route('quizzes.destroy', $otherQuiz))->assertForbidden();
    }

    public function test_gestor_can_update_and_delete_their_own_quiz(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);
        $quiz = Quiz::factory()->for($lesson)->create(['title' => 'Título Antigo']);

        $this->get(route('quizzes.edit', $quiz))
            ->assertOk()
            ->assertViewIs('quizzes.edit')
            ->assertSee('Título Antigo');

        $this->put(route('quizzes.update', $quiz), [
            'title' => 'Título Novo',
            'min_score_percentage' => 80,
        ])->assertRedirect(route('quizzes.edit', $quiz));
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'title' => 'Título Novo']);

        $this->delete(route('quizzes.destroy', $quiz))
            ->assertRedirect(route('modules.lessons.index', $lesson->module));
        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }

    // QuizQuestion + QuizOption CRUD

    public function test_gestor_can_create_a_single_choice_question_with_options(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $response = $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Quanto é 2 + 2?',
            'type' => 'single_choice',
            'options' => [
                ['option_text' => '4', 'is_correct' => true],
                ['option_text' => '5', 'is_correct' => false],
            ],
        ]);

        $response->assertRedirect(route('quizzes.edit', $quiz));
        $this->assertDatabaseHas('quiz_questions', ['quiz_id' => $quiz->id, 'question_text' => 'Quanto é 2 + 2?']);
        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame(2, $question->options()->count());
        $this->assertDatabaseHas('quiz_options', ['question_id' => $question->id, 'option_text' => '4', 'is_correct' => true]);
    }

    public function test_creating_an_essay_question_ignores_any_options_payload(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Disserte sobre X.',
            'type' => 'essay',
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('essay', $question->type);
        $this->assertSame(0, $question->options()->count());
    }

    public function test_single_choice_question_requires_exactly_one_correct_option(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Quanto é 2 + 2?',
            'type' => 'single_choice',
            'options' => [
                ['option_text' => '4', 'is_correct' => true],
                ['option_text' => '5', 'is_correct' => true],
            ],
        ])->assertSessionHasErrors('options');

        $this->assertSame(0, QuizQuestion::query()->where('quiz_id', $quiz->id)->count());
    }

    public function test_multiple_choice_question_requires_at_least_one_correct_option(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Quais são números pares?',
            'type' => 'multiple_choice',
            'options' => [
                ['option_text' => '1', 'is_correct' => false],
                ['option_text' => '3', 'is_correct' => false],
            ],
        ])->assertSessionHasErrors('options');

        $this->assertSame(0, QuizQuestion::query()->where('quiz_id', $quiz->id)->count());
    }

    public function test_gestor_can_update_a_question_upserting_and_removing_options(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $keptOption = QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Mantida']);
        $removedOption = QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'Removida']);

        $this->put(route('quiz-questions.update', $question), [
            'question_text' => 'Enunciado atualizado',
            'type' => 'single_choice',
            'options' => [
                ['id' => $keptOption->id, 'option_text' => 'Mantida', 'is_correct' => true],
                ['option_text' => 'Nova opção', 'is_correct' => false],
            ],
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question->refresh();
        $this->assertSame('Enunciado atualizado', $question->question_text);
        $this->assertDatabaseMissing('quiz_options', ['id' => $removedOption->id]);
        $this->assertDatabaseHas('quiz_options', ['id' => $keptOption->id, 'option_text' => 'Mantida']);
        $this->assertDatabaseHas('quiz_options', ['question_id' => $question->id, 'option_text' => 'Nova opção']);
        $this->assertSame(2, $question->options()->count());
    }

    public function test_updating_a_single_choice_question_rejects_a_wrong_count_of_correct_options(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $optionA = QuizOption::factory()->for($question, 'question')->correct()->create();
        $optionB = QuizOption::factory()->for($question, 'question')->correct()->create();

        $this->put(route('quiz-questions.update', $question), [
            'question_text' => 'Enunciado',
            'type' => 'single_choice',
            'options' => [
                ['id' => $optionA->id, 'option_text' => 'A', 'is_correct' => true],
                ['id' => $optionB->id, 'option_text' => 'B', 'is_correct' => true],
            ],
        ])->assertSessionHasErrors('options');
    }

    public function test_updating_a_multiple_choice_question_requires_at_least_one_correct_option(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->multipleChoice()->create();
        $optionA = QuizOption::factory()->for($question, 'question')->incorrect()->create();
        $optionB = QuizOption::factory()->for($question, 'question')->incorrect()->create();

        $this->put(route('quiz-questions.update', $question), [
            'question_text' => 'Enunciado',
            'type' => 'multiple_choice',
            'options' => [
                ['id' => $optionA->id, 'option_text' => 'A', 'is_correct' => false],
                ['id' => $optionB->id, 'option_text' => 'B', 'is_correct' => false],
            ],
        ])->assertSessionHasErrors('options');
    }

    public function test_updating_a_question_to_essay_type_deletes_its_existing_options(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        $this->put(route('quiz-questions.update', $question), [
            'question_text' => 'Disserte sobre o assunto.',
            'type' => 'essay',
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question->refresh();
        $this->assertSame('essay', $question->type);
        $this->assertSame(0, $question->options()->count());
    }

    public function test_gestor_can_delete_a_question(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $this->delete(route('quiz-questions.destroy', $question))
            ->assertRedirect(route('quizzes.edit', $quiz));

        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
    }

    public function test_gestor_can_reorder_questions_of_their_own_quiz(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $first = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['order_index' => 0]);
        $second = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['order_index' => 1]);

        $this->postJson(route('quiz-questions.reorder', $quiz), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }

    public function test_reordering_rejects_a_question_that_does_not_belong_to_the_quiz(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $foreignQuestion = QuizQuestion::factory()->for(Quiz::factory()->for($this->quizLesson($org)))->singleChoice()->create();

        $this->postJson(route('quiz-questions.reorder', $quiz), [
            'ordered_ids' => [$question->id, $foreignQuestion->id],
        ])->assertStatus(422);
    }

    // Essay manual grading screen

    public function test_gestor_sees_only_their_own_orgs_pending_essay_attempts(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        // Both fixtures are built while unauthenticated — `Course` forcibly
        // overwrites `org_id` from the currently authenticated user on
        // create (see `OrgScope::booted()`), so the "other org" fixture
        // must exist before `actingAsOrgUser()` logs a Gestor in, or its
        // Course would silently be reassigned to the Gestor's own Org.
        $this->awaitingManualGradingAttempt($otherOrg, 'Outra Org');

        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $ownAttempt = $this->awaitingManualGradingAttempt($org, 'Minha Org');

        $this->get(route('quiz-attempts.pending'))
            ->assertOk()
            ->assertViewIs('quizzes.attempts.pending')
            ->assertSee('Minha Org')
            ->assertDontSee('Outra Org');
    }

    public function test_aluno_is_forbidden_from_the_pending_essay_queue(): void
    {
        $this->actingAsOrgUser(role: RolesEnum::ALUNO->value);

        $this->get(route('quiz-attempts.pending'))->assertForbidden();
    }

    public function test_gestor_cannot_view_another_orgs_pending_attempt(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherAttempt = $this->awaitingManualGradingAttempt($otherOrg, 'Outra Org');
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->get(route('quiz-attempts.show', $otherAttempt))->assertForbidden();
    }

    public function test_gestor_can_grade_every_pending_essay_answer_and_the_attempt_is_finalized(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $attempt = $this->awaitingManualGradingAttempt($org, 'Minha Org');
        $essayAnswer = $attempt->answers()->whereNotNull('essay_answer')->firstOrFail();

        $this->get(route('quiz-attempts.show', $attempt))
            ->assertOk()
            ->assertViewIs('quizzes.attempts.show');

        $this->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ])->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertNotNull($attempt->score_percentage);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => true,
        ]);
    }

    public function test_gestor_cannot_grade_another_orgs_pending_attempt(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherAttempt = $this->awaitingManualGradingAttempt($otherOrg, 'Outra Org');
        $essayAnswer = $otherAttempt->answers()->whereNotNull('essay_answer')->firstOrFail();
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->post(route('quiz-attempts.grade', $otherAttempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ])->assertForbidden();

        $this->assertDatabaseHas('quiz_answers', ['id' => $essayAnswer->id, 'is_correct' => null]);
    }

    public function test_aluno_cannot_view_or_grade_a_pending_attempt(): void
    {
        $org = Organization::factory()->create();
        $attempt = $this->awaitingManualGradingAttempt($org, 'Minha Org');
        $essayAnswer = $attempt->answers()->whereNotNull('essay_answer')->firstOrFail();
        $this->actingAsOrgUser(role: RolesEnum::ALUNO->value);

        $this->get(route('quiz-attempts.show', $attempt))->assertForbidden();
        $this->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ])->assertForbidden();
    }

    // StudentQuizController::submit() — fully auto-graded paths + guardrail errors

    public function test_a_fully_auto_graded_submission_that_passes_redirects_with_a_success_message(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($lesson->module->course_id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $this->post(route('student.quizzes.submit', $lesson), [
            'answers' => [
                $question->id => ['selected_option_ids' => [$correctOption->id]],
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('classroom.lesson', $lesson))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'concluída com sucesso'));

        $attempt = $quiz->fresh()->attempts()->where('user_id', $aluno->id)->firstOrFail();
        $this->assertSame('graded', $attempt->status);
        $this->assertTrue($attempt->is_passed);
    }

    public function test_a_fully_auto_graded_submission_that_fails_redirects_with_a_failure_message(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 70]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->correct()->create();
        $wrongOption = QuizOption::factory()->for($question, 'question')->incorrect()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($lesson->module->course_id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $this->post(route('student.quizzes.submit', $lesson), [
            'answers' => [
                $question->id => ['selected_option_ids' => [$wrongOption->id]],
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('classroom.lesson', $lesson))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'não atingiu a nota mínima'));

        $attempt = $quiz->fresh()->attempts()->where('user_id', $aluno->id)->firstOrFail();
        $this->assertSame('graded', $attempt->status);
        $this->assertFalse($attempt->is_passed);
    }

    public function test_submitting_beyond_the_attempt_limit_is_rejected_with_validation_errors(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $lesson = $this->quizLesson($org);
        $quiz = Quiz::factory()->for($lesson)->create(['allow_retries' => false]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($lesson->module->course_id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($aluno);

        $payload = ['answers' => [$question->id => ['selected_option_ids' => [$correctOption->id]]]];

        $this->post(route('student.quizzes.submit', $lesson), $payload)->assertSessionHasNoErrors();
        $this->post(route('student.quizzes.submit', $lesson), $payload)->assertSessionHasErrors('quiz');

        $this->assertSame(1, $quiz->fresh()->attempts()->where('user_id', $aluno->id)->count());
    }
}
