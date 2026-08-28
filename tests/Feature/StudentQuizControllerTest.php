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

class StudentQuizControllerTest extends TestCase
{
    private function createQuizSetup(array $quizAttributes = []): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(array_merge([
            'title' => 'Avaliação Final',
            'min_score_percentage' => 70,
            'allow_retries' => true,
        ], $quizAttributes));

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return [$aluno, $lesson, $quiz, $course];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        $this->get(route('student.quizzes.show', $lesson))
            ->assertRedirect(route('login'));
    }

    public function test_unenrolled_student_is_sent_back_to_the_catalog_instead_of_the_quiz(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        /** @var User $otherAluno */
        $otherAluno = User::factory()->create(['org_id' => null]);
        $otherAluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($otherAluno)->get(route('student.quizzes.show', $lesson));

        $response->assertRedirect(route('student.courses.index'));
        $response->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_enrolled_student_can_view_quiz_page(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['question_text' => 'Qual é a capital do Brasil?']);
        QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'Rio de Janeiro']);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Avaliação Final')
            ->assertSee('Qual é a capital do Brasil?')
            ->assertSee('Brasília')
            ->assertSee('Rio de Janeiro');
    }

    public function test_quiz_page_shows_best_score_banner_when_attempt_exists(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 85.5,
            'is_passed' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Sua melhor nota nesta prova: 85.5%');
    }

    public function test_quiz_page_shows_pending_grading_banner_when_essay_is_awaiting_grading(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'awaiting_manual_grading',
            'score_percentage' => null,
            'is_passed' => null,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Você possui uma tentativa aguardando correção manual.');
    }

    public function test_quiz_page_shows_answer_key_when_enabled_and_graded_attempt_exists(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correctOption->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Gabarito');
    }

    public function test_quiz_page_shows_cannot_attempt_when_retries_disabled_and_attempt_exists(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['allow_retries' => false]);

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 50,
            'is_passed' => false,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Esta prova não permite novas tentativas.');
    }

    public function test_quiz_page_shows_cannot_attempt_when_max_attempts_reached(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['allow_retries' => true, 'max_attempts' => 2]);

        QuizAttempt::factory()->count(2)->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 40,
            'is_passed' => false,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Você atingiu o número máximo de tentativas (2) para esta prova.');
    }

    public function test_enrolled_student_can_submit_auto_graded_quiz(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['min_score_percentage' => 70]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('classroom.lesson', $lesson))
            ->assertSessionHas('success', fn (string $msg) => str_contains($msg, 'Prova concluída com sucesso!'));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $aluno->id,
            'status' => 'graded',
            'is_passed' => true,
        ]);
    }

    public function test_submitting_beyond_attempt_limit_redirects_back_with_validation_errors(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['allow_retries' => false]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 100,
            'is_passed' => true,
        ]);

        $this->actingAs($aluno)
            ->from(route('student.quizzes.show', $lesson))
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertRedirect(route('student.quizzes.show', $lesson))
            ->assertSessionHasErrors('quiz');
    }
}
