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
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function test_quiz_page_renders_the_timer_and_started_at_contract_when_a_time_limit_is_set(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['time_limit_minutes' => 30]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $response = $this->actingAs($aluno)->get(route('student.quizzes.show', $lesson));

        $response->assertOk()
            ->assertSee('data-quiz-timer', false)
            ->assertSee('data-time-limit-minutes="30"', false)
            ->assertSee('data-started-at="', false)
            ->assertDontSee('name="started_at"', false);

        // O início da tentativa é carimbado no servidor ao abrir a tela.
        $openAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $response->assertSee('data-started-at="'.$openAttempt->started_at->toIso8601String().'"', false);
    }

    /**
     * Recarregar a tela não reinicia a contagem: a tentativa aberta é
     * reaproveitada, mantendo o `started_at` original.
     */
    public function test_reloading_the_quiz_page_reuses_the_open_attempt_start_time(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['time_limit_minutes' => 30]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $openAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('data-started-at="'.$openAttempt->started_at->toIso8601String().'"', false);

        $this->assertSame(1, QuizAttempt::query()->where('quiz_id', $quiz->id)->count());
    }

    /**
     * Uma tentativa aberta e abandonada, cujo tempo já se esgotou, é
     * encerrada ao reabrir a tela: vira tentativa corrigida (zero,
     * reprovada) e dá lugar a uma nova tentativa com cronômetro próprio.
     * Assim a linha `in_progress` nunca fica pendurada, invisível para o
     * Aluno e para o Gestor.
     */
    public function test_opening_the_quiz_page_expires_an_abandoned_attempt_and_starts_a_new_one(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 30,
            'allow_retries' => true,
        ]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $abandonedAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subDays(2),
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('O tempo da sua tentativa anterior se esgotou antes do envio.')
            ->assertDontSee('data-started-at="'.$abandonedAttempt->started_at->toIso8601String().'"', false);

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $abandonedAttempt->id,
            'status' => 'graded',
            'score_percentage' => 0,
            'is_passed' => false,
        ]);

        $this->assertSame(
            $abandonedAttempt->started_at->copy()->addMinutes(30)->toDateTimeString(),
            $abandonedAttempt->fresh()->completed_at->toDateTimeString(),
            'A tentativa expirada deve ser encerrada no instante do prazo, não no da reabertura.',
        );

        $newAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->assertNotSame($abandonedAttempt->id, $newAttempt->id);
        $this->assertTrue($newAttempt->started_at->greaterThan(now()->subMinute()));
    }

    /**
     * Recomeçar depois do estouro custa uma tentativa: quando a prova não
     * permite repetição, a tentativa abandonada é encerrada como reprovada
     * e a tela passa a bloquear novos envios — nunca oferece um cronômetro
     * novo de graça.
     */
    public function test_an_expired_attempt_is_consumed_and_blocks_a_quiz_without_retries(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 10,
            'allow_retries' => false,
        ]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subHours(5),
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('O tempo da sua tentativa anterior se esgotou antes do envio.')
            ->assertSee('Esta prova não permite novas tentativas.')
            ->assertDontSee('data-quiz-timer', false);

        $this->assertSame(0, QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->where('status', 'in_progress')
            ->count());

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertSessionHasErrors('quiz');

        $this->assertSame(1, QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->count());
    }

    /**
     * O caminho real do aluno que estoura o tempo: abre a tela, deixa o
     * cronômetro zerar e só então envia. O `started_at` carimbado na
     * abertura decide o estouro e a tentativa é corrigida sem aprovação,
     * mesmo com 100% de acerto.
     */
    public function test_a_submission_after_the_countdown_ends_is_graded_without_approval(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 70,
            'allow_retries' => true,
        ]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        $this->actingAs($aluno)->get(route('student.quizzes.show', $lesson))->assertOk();

        $openAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->travel(40)->minutes();

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('classroom.lesson', $lesson));

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $openAttempt->id,
            'status' => 'graded',
            'score_percentage' => 100.00,
            'is_passed' => false,
        ]);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);

        $this->travelBack();
    }

    /**
     * A garantia de "no máximo uma tentativa aberta por prova e aluno" é
     * do banco, não de uma trava sobre uma linha que ainda não existe:
     * inserir uma segunda tentativa `in_progress` é rejeitado pelo índice
     * único, mesmo passando por baixo da Action.
     */
    public function test_the_database_rejects_a_second_open_attempt_for_the_same_quiz_and_student(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['time_limit_minutes' => 30]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();
    }

    /**
     * O índice único vale apenas para tentativas abertas: um histórico de
     * tentativas corrigidas do mesmo aluno na mesma prova continua válido.
     */
    public function test_completed_attempts_of_the_same_student_never_collide(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        QuizAttempt::factory()->for($quiz)->for($aluno)->graded()->count(3)->create();
        QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()->create();

        $this->assertSame(4, QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->count());
    }

    /**
     * Uma tentativa que se encerra libera o "lugar" de tentativa aberta:
     * fechar a anterior e abrir a próxima não esbarra no índice único.
     */
    public function test_closing_an_attempt_frees_the_open_slot_for_the_next_one(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['time_limit_minutes' => 30]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $firstAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();
        $firstAttempt->update([
            'status' => 'graded',
            'score_percentage' => 40,
            'is_passed' => false,
            'completed_at' => now(),
        ]);

        $this->actingAs($aluno)->get(route('student.quizzes.show', $lesson))->assertOk();

        $this->assertSame(1, QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $aluno->id)
            ->where('status', 'in_progress')
            ->count());
    }

    public function test_a_stale_server_side_start_makes_the_submission_late_and_never_passing(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subMinutes(45),
        ]);

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('classroom.lesson', $lesson));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $aluno->id,
            'status' => 'graded',
            'is_passed' => false,
        ]);
    }

    /**
     * Um `started_at` forjado no POST é ignorado: o estouro de tempo é
     * decidido pelo `started_at` persistido da tentativa aberta.
     */
    public function test_a_forged_started_at_cannot_rescue_a_late_submission(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subMinutes(45),
        ]);

        $this->actingAs($aluno)
            ->post(route('student.quizzes.submit', $lesson), [
                'started_at' => now()->toIso8601String(),
                'answers' => [
                    $question->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertRedirect(route('classroom.lesson', $lesson));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $aluno->id,
            'status' => 'graded',
            'is_passed' => false,
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

    /**
     * Toda questão é obrigatória: deixar uma sem resposta devolve à prova
     * com erro apontando a questão faltante e nenhuma tentativa é registrada —
     * o servidor não confia no bloqueio do botão feito no navegador.
     */
    public function test_a_submission_missing_a_question_is_rejected_and_records_no_attempt(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $answeredQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correctOption = QuizOption::factory()->for($answeredQuestion, 'question')->correct()->create();
        $skippedQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $this->actingAs($aluno)
            ->from(route('student.quizzes.show', $lesson))
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $answeredQuestion->id => ['selected_option_ids' => [$correctOption->id]],
                ],
            ])
            ->assertRedirect(route('student.quizzes.show', $lesson))
            ->assertSessionHasErrors('answers.'.$skippedQuestion->id.'.selected_option_ids');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_a_submission_with_a_blank_essay_answer_is_rejected_and_records_no_attempt(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $this->actingAs($aluno)
            ->from(route('student.quizzes.show', $lesson))
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $essayQuestion->id => ['essay_answer' => '   '],
                ],
            ])
            ->assertRedirect(route('student.quizzes.show', $lesson))
            ->assertSessionHasErrors('answers.'.$essayQuestion->id.'.essay_answer');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_the_confirmation_dialog_lives_outside_the_quiz_form_and_submits_it_by_id(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $html = $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->getContent();

        $formStart = strpos($html, 'id="quiz-attempt-form"');
        $formEnd = strpos($html, '</form>', $formStart);
        $modalStart = strpos($html, 'data-quiz-confirm-modal');

        $this->assertNotFalse($formStart);
        $this->assertNotFalse($modalStart);
        $this->assertGreaterThan($formEnd, $modalStart, 'O modal de confirmação não pode ficar aninhado dentro do formulário da prova.');
        $this->assertStringContainsString('form="quiz-attempt-form"', $html);
        $this->assertStringContainsString('data-quiz-required-hint', $html);
        $this->assertSame(0, substr_count(substr($html, $formStart, $formEnd - $formStart), '<form'), 'O formulário da prova não pode conter formulários aninhados.');
    }

    public function test_the_attempt_counter_sentence_is_suppressed_when_attempts_are_unlimited(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['allow_retries' => true, 'max_attempts' => null]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('data-quiz-confirm-modal', false)
            ->assertDontSee('Esta é a tentativa');
    }

    public function test_the_timer_is_not_rendered_when_the_student_can_no_longer_attempt(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup([
            'time_limit_minutes' => 30,
            'allow_retries' => true,
            'max_attempts' => 1,
        ]);

        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'graded',
            'score_percentage' => 20,
            'is_passed' => false,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertDontSee('data-quiz-timer', false)
            ->assertSee('Você atingiu o número máximo de tentativas (1) para esta prova.');
    }

    public function test_an_essay_answer_is_repopulated_after_a_validation_bounce(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup();

        $question = QuizQuestion::factory()->for($quiz)->essay()->create();

        $this->actingAs($aluno)
            ->from(route('student.quizzes.show', $lesson))
            ->post(route('student.quizzes.submit', $lesson), [
                'answers' => [
                    $question->id => [
                        'essay_answer' => 'Minha dissertação preservada.',
                        // Opção inexistente: força o bounce de validação.
                        'selected_option_ids' => [999999],
                    ],
                ],
            ])
            ->assertSessionHasErrors('answers.'.$question->id.'.selected_option_ids.0');

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Minha dissertação preservada.', false);
    }

    public function test_the_answer_key_is_hidden_while_an_attempt_is_awaiting_manual_grading(): void
    {
        [$aluno, $lesson, $quiz] = $this->createQuizSetup(['show_correct_answers' => true]);

        QuizQuestion::factory()->for($quiz)->essay()->create();

        QuizAttempt::factory()->for($quiz)->for($aluno)->create([
            'status' => 'awaiting_manual_grading',
            'score_percentage' => null,
            'is_passed' => null,
        ]);

        $this->actingAs($aluno)
            ->get(route('student.quizzes.show', $lesson))
            ->assertOk()
            ->assertSee('Você possui uma tentativa aguardando correção manual.')
            ->assertDontSee('Gabarito');
    }
}
