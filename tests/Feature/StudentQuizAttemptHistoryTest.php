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
 * Contrato Feature do histórico de tentativas (`student.quizzes.history`)
 * e do resultado por tentativa (`student.quizzes.attempt-result`).
 *
 * O histórico lista TODAS as attempts do próprio Aluno, mais recente
 * primeiro, com numeração cronológica ("Tentativa 1" = mais antiga). O
 * resultado por tentativa é autorizado por posse (user_id + quiz da
 * lesson — nunca `QuizAttemptPolicy`, que é staff-only) e 404 para
 * attempt de outro aluno, de outra prova ou `in_progress`.
 */
class StudentQuizAttemptHistoryTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        $this->get(route('student.quizzes.history', $lesson))
            ->assertRedirect(route('login'));
    }

    public function test_unenrolled_student_is_sent_back_to_the_catalog(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        /** @var User $otherAluno */
        $otherAluno = User::factory()->inOrg($lesson->module->course->org_id)->create();
        $otherAluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->actingAs($otherAluno)->get(route('student.quizzes.history', $lesson));

        $response->assertRedirect(route('student.courses.index'));
        $response->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_history_lists_attempts_newest_first_with_chronological_numbers(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $oldest = QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create([
            'started_at' => now()->subDays(3),
            'score_percentage' => 40.00,
            'is_passed' => false,
        ]);
        $middle = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()->create([
            'started_at' => now()->subDays(2),
        ]);
        $newest = QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create([
            'started_at' => now()->subDay(),
            'score_percentage' => 85.50,
            'is_passed' => true,
        ]);

        $response = $this->actingAs($aluno)
            ->get(route('student.quizzes.history', $lesson))
            ->assertOk()
            ->assertSee('attempt-history', false)
            ->assertSee('Tentativa 1')
            ->assertSee('Tentativa 2')
            ->assertSee('Tentativa 3')
            ->assertSee('Aprovada')
            ->assertSee('Reprovada')
            ->assertSee('Aguardando correção')
            ->assertSee('85.50%', false)
            ->assertSee('40.00%', false);

        // Mais recente primeiro: o row da última attempt aparece antes na página.
        $html = (string) $response->getContent();
        $this->assertLessThan(
            strpos($html, 'attempt-row-'.$oldest->id),
            strpos($html, 'attempt-row-'.$newest->id),
            'O histórico lista as tentativas da mais recente para a mais antiga.',
        );
    }

    public function test_history_shows_the_in_progress_attempt_as_status_only_without_result_link(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $openAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.history', $lesson))
            ->assertOk()
            ->assertSee('Em andamento')
            ->assertSee('attempt-row-'.$openAttempt->id, false)
            ->assertDontSee('attempt-result-link-'.$openAttempt->id, false)
            ->assertSee('—');
    }

    public function test_history_shows_the_empty_state_when_the_student_has_no_attempts(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.history', $lesson))
            ->assertOk()
            ->assertSee('attempt-history-empty', false)
            ->assertSee('Você ainda não tentou este quiz.')
            ->assertDontSee('attempt-row-', false);
    }

    public function test_the_quiz_screen_shows_the_history_link_only_when_an_attempt_exists(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertDontSee('attempt-history-link', false);

        QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('attempt-history-link', false)
            ->assertSee('Minhas tentativas');
    }

    public function test_the_attempt_result_shows_that_specific_attempt_answer_key(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['question_text' => 'Questão única.']);
        $correct = QuizOption::factory()->for($question, 'question')->correct()->create();

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create([
            'score_percentage' => 100,
            'is_passed' => true,
        ]);
        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.attempt-result', [$lesson, $attempt]))
            ->assertOk()
            ->assertSee('quiz-result', false)
            ->assertSee('quiz-answer-key', false)
            ->assertSee('Gabarito')
            ->assertSee('100.00%', false)
            ->assertSee('attempt-history-link', false)
            ->assertSee('Minhas tentativas');
    }

    public function test_the_attempt_result_of_another_students_attempt_is_not_found(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        /** @var User $otherAluno */
        $otherAluno = User::factory()->inOrg($quiz->lesson->module->course->org_id)->create();
        $otherAluno->assignRole(RolesEnum::ALUNO->value);
        $otherAluno->courses()->attach($quiz->lesson->module->course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $attempt = QuizAttempt::factory()->for($quiz)->for($otherAluno)->graded()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.attempt-result', [$lesson, $attempt]))
            ->assertNotFound();
    }

    public function test_the_attempt_result_of_an_attempt_from_another_quiz_is_not_found(): void
    {
        [$aluno, $lesson] = $this->createQuizSetup();

        $otherLesson = Lesson::factory()->for($lesson->module)->create(['type' => 'quiz', 'is_published' => true]);
        $otherQuiz = Quiz::factory()->for($otherLesson)->create();

        $attempt = QuizAttempt::factory()->for($otherQuiz)->for($aluno)->graded()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.attempt-result', [$lesson, $attempt]))
            ->assertNotFound();
    }

    public function test_the_attempt_result_of_an_in_progress_attempt_is_not_found(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.attempt-result', [$lesson, $attempt]))
            ->assertNotFound();
    }

    public function test_the_untagged_result_route_still_shows_the_latest_finished_attempt(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $oldAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create([
            'started_at' => now()->subDays(2),
            'score_percentage' => 40,
            'is_passed' => false,
        ]);
        $latestAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->create([
            'started_at' => now()->subDay(),
            'score_percentage' => 90,
            'is_passed' => true,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.result', $lesson))
            ->assertOk()
            ->assertSee('90.00%', false)
            ->assertDontSee('40.00%', false);

        // O resultado da mais antiga continua acessível pela rota por attempt.
        $this->actingAs($aluno)
            ->get(route('student.quizzes.attempt-result', [$lesson, $oldAttempt]))
            ->assertOk()
            ->assertSee('40.00%', false);
    }
}
