<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * Contrato Feature da tela de resultado (`student.quizzes.result`) e do
 * redirect pós-envio (`classroom.lesson` → `student.quizzes.result`).
 *
 * O gabarito mora aqui (pós-envio) e no estado bloqueado (`!$canAttempt`);
 * nunca no form/confirmação com retentativa disponível. Seletores e textos
 * seguem a spec (86e361vp7, Frente C): `quiz-result`, `quiz-result-score`,
 * `back-to-course`, "Você acertou X%", "Aguardando correção".
 */
class StudentQuizResultFlowTest extends TestCase
{
    private function createQuizSetup(array $quizAttributes = []): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(array_merge([
            'title' => 'Avaliação Final',
            'min_score_percentage' => 70,
            'allow_retries' => true,
        ], $quizAttributes));

        /** @var User $aluno */
        $aluno = User::factory()->inOrg($org->id)->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return [$aluno, $lesson, $quiz, $course];
    }

    private function singleChoiceQuestion(Quiz $quiz): array
    {
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        return [$question, $correct];
    }

    public function test_an_auto_graded_submission_redirects_to_the_result_screen(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();
        [$question, $correct] = $this->singleChoiceQuestion($quiz);

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correct->id]],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('student.quizzes.result', $lesson));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $aluno->id,
            'status' => 'graded',
            'is_passed' => true,
        ]);
    }

    public function test_an_essay_submission_redirects_to_the_result_screen_as_awaiting_grading(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();
        QuizQuestion::factory()->for($quiz)->essay()->create();

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $quiz->questions->first()->id => ['essay_answer' => 'Resposta dissertativa do aluno.'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('student.quizzes.result', $lesson))
            ->assertSessionHas('success', fn (string $msg) => str_contains($msg, 'aguardam correção manual'));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $aluno->id,
            'status' => 'awaiting_manual_grading',
        ]);
    }

    public function test_the_result_screen_shows_the_score_and_a_back_to_course_button(): void
    {
        [$aluno, $lesson, $quiz, $course] = $this->createQuizSetup(['show_correct_answers' => false]);
        [$question, $correct] = $this->singleChoiceQuestion($quiz);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);
        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('quiz-result', false)
            ->assertSee('quiz-result-score', false)
            ->assertSee('Você acertou', false)
            ->assertSee('100.00%', false)
            ->assertSee('back-to-course', false)
            ->assertSee(route('classroom.show', $course), false);
    }

    public function test_the_result_screen_shows_the_answer_key_only_when_enabled(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);
        [$question, $correct] = $this->singleChoiceQuestion($quiz);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);
        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('quiz-answer-key', false)
            ->assertSee('answer-key-question-'.$question->id, false)
            ->assertSee('Gabarito');
    }

    public function test_the_answer_key_renders_the_essay_answer_flush_inside_the_pre_wrap_block(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'awaiting_manual_grading',
        ]);
        $attempt->answers()->create([
            'question_id' => $essayQuestion->id,
            'essay_answer' => 'Resposta dissertativa do aluno.',
        ]);

        // `.text-prewrap` preserva quebras: o `{{ }}` precisa estar colado
        // às tags, sem indentação do template no meio.
        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('<strong>Sua resposta:</strong> Resposta dissertativa do aluno.</div>', false);
    }

    public function test_the_result_screen_hides_the_answer_key_when_disabled(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => false]);
        [$question, $correct] = $this->singleChoiceQuestion($quiz);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);
        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('quiz-result-score', false)
            ->assertDontSee('quiz-answer-key', false)
            ->assertDontSee('answer-key-', false)
            ->assertDontSee('Gabarito');
    }

    public function test_the_result_screen_of_an_attempt_awaiting_grading_has_no_score(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'awaiting_manual_grading',
            'score_percentage' => null,
            'is_passed' => null,
        ]);
        $attempt->answers()->create([
            'question_id' => $essayQuestion->id,
            'essay_answer' => 'Resposta dissertativa do aluno.',
            'is_correct' => null,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('quiz-result', false)
            ->assertDontSee('quiz-result-score', false)
            ->assertSee('Aguardando correção');
    }

    public function test_the_result_screen_without_any_attempt_redirects_back_to_the_quiz(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertRedirect(route('student.quizzes.show', $lesson));
    }

    public function test_the_result_screen_shows_the_latest_attempt(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => false]);
        [$question, $correct] = $this->singleChoiceQuestion($quiz);

        $oldAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 0,
            'is_passed' => false,
        ]);
        $oldAttempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => false,
        ]);

        $latestAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);
        $latestAttempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('quiz-result-score', false)
            ->assertSee('100.00%', false);
    }
}
