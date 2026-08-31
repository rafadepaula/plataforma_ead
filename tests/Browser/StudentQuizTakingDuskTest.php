<?php

namespace Tests\Browser;

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
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the Aluno's single-page quiz-taking
 * screen: opening it from the classroom's quiz-lesson placeholder,
 * answering every question in one submission, and seeing the resulting
 * grade/lesson-completion feedback.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada auto-corrigida (abrir da sala → responder → aprovar → lição
 * concluída), a jornada dissertativa (aguardando correção manual) e os
 * estados de bloqueio da tela (tentativas esgotadas, tempo esgotado,
 * reprovação e gabarito) — cada uma num único método, sempre com o mesmo Aluno.
 */
class StudentQuizTakingDuskTest extends DuskTestCase
{
    public function test_student_auto_graded_quiz_attempt_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $course, $lesson, $correctOption): void {
            // 1. Abrir o quiz a partir da sala de aula e responder.
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@open-lesson-'.$lesson->id)
                ->click('@open-lesson-'.$lesson->id)
                ->waitFor('@start-quiz')
                ->click('@start-quiz')
                ->waitFor('@quiz-attempt-form')
                ->click('@quiz-option-'.$correctOption->question_id.'-'.$correctOption->id)
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('concluída com sucesso');
        });

        // 2. Consequências no banco: tentativa aprovada e lição concluída
        //    pela fonte `quiz_passed`.
        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $lesson->quiz?->id ?? $quiz->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'is_passed' => 1,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'quiz_passed',
        ]);
    }

    /**
     * A metade "múltipla escolha" do contrato de cartões de resposta: os
     * controles são checkboxes de seleção múltipla (não rádios), o cartão
     * de cada opção marcada ganha `.is-selected`, e a correção só aceita o
     * conjunto completo de opções corretas.
     */
    public function test_student_multiple_choice_question_requires_every_correct_option(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create([
            'min_score_percentage' => 100,
            'allow_retries' => true,
        ]);

        $question = QuizQuestion::factory()->for($quiz)->multipleChoice()->create([
            'question_text' => 'Quais destas cidades já foram capitais do Brasil?',
        ]);
        $firstCorrectOption = QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Salvador']);
        $secondCorrectOption = QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Rio de Janeiro']);
        $incorrectOption = QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $lesson, $question, $firstCorrectOption, $secondCorrectOption, $incorrectOption): void {
            $firstOptionSelector = '@quiz-option-'.$question->id.'-'.$firstCorrectOption->id;
            $secondOptionSelector = '@quiz-option-'.$question->id.'-'.$secondCorrectOption->id;
            $incorrectOptionSelector = '@quiz-option-'.$question->id.'-'.$incorrectOption->id;

            // 1. Uma resposta parcial (só uma das corretas) é reprovada: o
            //    conjunto marcado precisa bater com o conjunto correto inteiro.
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-attempt-form')
                ->assertSee('Selecione todas as respostas que se aplicam')
                ->assertAttribute($firstOptionSelector, 'type', 'checkbox')
                ->click($firstOptionSelector)
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('não atingiu a nota mínima');

            // 2. Nova tentativa: marcar duas opções mantém as duas marcadas
            //    (checkbox, não rádio) e ambos os cartões destacados.
            $browser->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-attempt-form')
                ->click($firstOptionSelector)
                ->click($secondOptionSelector)
                ->assertChecked($firstOptionSelector)
                ->assertChecked($secondOptionSelector)
                ->assertNotChecked($incorrectOptionSelector);

            $selectedCards = $browser->script(
                "return Array.prototype.slice.call(document.querySelectorAll('.quiz-option-card.is-selected')).length;"
            )[0];

            $this->assertSame(2, $selectedCards, 'Cada opção marcada deve destacar o próprio cartão.');

            $browser->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('concluída com sucesso');
        });

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score_percentage' => 100.00,
            'is_passed' => 1,
        ]);
        $this->assertSame(1, QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->where('is_passed', false)
            ->count());
    }

    public function test_student_essay_quiz_awaits_manual_grading(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create([
            'question_text' => 'Disserte sobre o assunto estudado.',
        ]);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $lesson, $essayQuestion): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-attempt-form')
                ->type('@quiz-essay-'.$essayQuestion->id, 'Minha resposta dissertativa completa.')
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('aguardam correção manual');
        });

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'status' => 'awaiting_manual_grading',
        ]);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    /**
     * O envio é sempre em duas etapas: o botão "Finalizar prova" abre a
     * confirmação, que anuncia quantas questões ficaram sem resposta antes
     * de o Aluno confirmar — e só a confirmação submete a prova.
     */
    public function test_confirmation_dialog_announces_unanswered_questions_before_submitting(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $answeredQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        $correctOption = QuizOption::factory()->for($answeredQuestion, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($answeredQuestion, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        $skippedQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital de Portugal?',
        ]);
        QuizOption::factory()->for($skippedQuestion, 'question')->correct()->create(['option_text' => 'Lisboa']);
        QuizOption::factory()->for($skippedQuestion, 'question')->incorrect()->create(['option_text' => 'Porto']);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $lesson, $correctOption): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-attempt-form')
                ->click('@quiz-option-'.$correctOption->question_id.'-'.$correctOption->id)
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                // Abrir a confirmação não submete nada por conta própria.
                ->assertPresent('@quiz-attempt-form')
                ->assertSeeIn('@confirm-modal-submit-attempt-modal', '1 de 2')
                ->assertSeeIn('@confirm-modal-submit-attempt-modal', 'sem resposta')
                ->assertSeeIn('@confirm-modal-submit-attempt-modal', 'não será possível alterar')
                ->click('@quiz-attempt-confirm')
                ->waitForText('concluída com sucesso');
        });

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $lesson->quiz?->id,
            'user_id' => $student->id,
        ]);
    }

    /**
     * Isolamento por matrícula/organização no navegador: o Aluno
     * matriculado no curso da organização A não abre nem submete a prova
     * de um curso da organização B — é devolvido ao catálogo e nenhuma
     * tentativa é registrada.
     */
    public function test_student_cannot_open_a_quiz_of_a_course_from_another_organization(): void
    {
        $ownOrg = Organization::factory()->create();
        $ownCourse = Course::factory()->create(['org_id' => $ownOrg->id, 'is_published' => true]);

        $foreignOrg = Organization::factory()->create();
        $foreignCourse = Course::factory()->create(['org_id' => $foreignOrg->id, 'is_published' => true]);
        $foreignModule = Module::factory()->for($foreignCourse)->create();
        $foreignLesson = Lesson::factory()->for($foreignModule)->create(['type' => 'quiz', 'is_published' => true]);
        $foreignQuiz = Quiz::factory()->for($foreignLesson)->create();
        $foreignQuestion = QuizQuestion::factory()->for($foreignQuiz)->singleChoice()->create();
        QuizOption::factory()->for($foreignQuestion, 'question')->correct()->create();

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $ownCourse->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $foreignLesson): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $foreignLesson))
                ->waitForLocation('/meus-cursos')
                ->assertSee('Acesso negado')
                ->assertMissing('@quiz-attempt-form');
        });

        $this->assertDatabaseMissing('quiz_attempts', [
            'quiz_id' => $foreignQuiz->id,
            'user_id' => $student->id,
        ]);
    }

    /**
     * Os estados de bloqueio/feedback da tela do Aluno, percorridos
     * pelo mesmo Aluno numa única sessão de navegador — cada um com sua
     * própria configuração de `Quiz` no mesmo Curso.
     */
    public function test_student_quiz_screen_gating_states(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        // (a) Quiz sem novas tentativas, já com uma tentativa corrigida e
        //     outra aguardando correção manual: exercita a faixa de avisos
        //     completa (melhor nota → correção pendente → bloqueio → voltar).
        $noRetryLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $noRetryQuiz = Quiz::factory()->for($noRetryLesson)->create([
            'allow_retries' => false,
            'show_correct_answers' => false,
        ]);
        QuizQuestion::factory()->for($noRetryQuiz)->singleChoice()->create();
        QuizAttempt::factory()->for($noRetryQuiz)->for($student)->graded()->create([
            'score_percentage' => 82.50,
            'is_passed' => true,
        ]);
        QuizAttempt::factory()->for($noRetryQuiz)->for($student)->awaitingManualGrading()->create();

        // (b) Quiz com limite de tempo (o contador é cosmético — ver abaixo).
        $timedLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $timedQuiz = Quiz::factory()->for($timedLesson)->create(['time_limit_minutes' => 1]);
        $timedQuestion = QuizQuestion::factory()->for($timedQuiz)->singleChoice()->create();
        $timedCorrectOption = QuizOption::factory()->for($timedQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($timedQuestion, 'question')->incorrect()->create();

        // (c) Quiz que permite repetição, porém com `max_attempts` atingido.
        $maxAttemptsLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $maxAttemptsQuiz = Quiz::factory()->for($maxAttemptsLesson)->create([
            'allow_retries' => true,
            'max_attempts' => 2,
        ]);
        QuizQuestion::factory()->for($maxAttemptsQuiz)->singleChoice()->create();
        QuizAttempt::factory()->for($maxAttemptsQuiz)->for($student)->graded()->count(2)->create();

        // (d) Quiz com gabarito liberado após a tentativa corrigida.
        $answerKeyLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $answerKeyQuiz = Quiz::factory()->for($answerKeyLesson)->create(['show_correct_answers' => true]);
        $answerKeyQuestion = QuizQuestion::factory()->for($answerKeyQuiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        $answerKeyCorrectOption = QuizOption::factory()->for($answerKeyQuestion, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($answerKeyQuestion, 'question')->incorrect()->create(['option_text' => 'São Paulo']);
        QuizAttempt::factory()->for($answerKeyQuiz)->for($student)->graded()->create();

        // (e) Quiz reprovável: a nota mínima é 100, então a resposta errada
        //     cai no ramo "Reprovado" da tela de resultado.
        $failingLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $failingQuiz = Quiz::factory()->for($failingLesson)->create(['min_score_percentage' => 100]);
        $failingQuestion = QuizQuestion::factory()->for($failingQuiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        QuizOption::factory()->for($failingQuestion, 'question')->correct()->create(['option_text' => 'Brasília']);
        $failingWrongOption = QuizOption::factory()->for($failingQuestion, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        // (f) Gabarito bloqueado: `show_correct_answers` desligado, mesmo já
        //     havendo tentativa corrigida.
        $hiddenKeyLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $hiddenKeyQuiz = Quiz::factory()->for($hiddenKeyLesson)->create(['show_correct_answers' => false]);
        QuizQuestion::factory()->for($hiddenKeyQuiz)->singleChoice()->create();
        QuizAttempt::factory()->for($hiddenKeyQuiz)->for($student)->graded()->create();

        $this->browse(function (Browser $browser) use (
            $student,
            $course,
            $noRetryLesson,
            $timedLesson,
            $timedQuiz,
            $timedCorrectOption,
            $maxAttemptsLesson,
            $maxAttemptsQuiz,
            $answerKeyLesson,
            $answerKeyCorrectOption,
            $failingLesson,
            $failingWrongOption,
            $hiddenKeyLesson
        ): void {
            // 1. Tentativas esgotadas: formulário some, sem 500, e a faixa de
            //    avisos aparece na ordem estrita, terminando na saída para a
            //    sala de aula.
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $noRetryLesson))
                ->waitFor('@quiz-cannot-attempt')
                ->assertSee('não permite novas tentativas')
                ->assertMissing('@quiz-attempt-form')
                ->assertSeeIn('@quiz-best-score', 'Sua melhor nota nesta prova: 82.5%')
                ->assertSeeIn('@quiz-pending-grading', 'aguardando correção manual')
                ->assertVisible('@back-to-lesson')
                ->assertAttribute('@back-to-lesson', 'href', route('classroom.show', $course));

            // O botão de saída realmente devolve o Aluno à sala de aula.
            $browser->click('@back-to-lesson')
                ->waitForLocation(parse_url(route('classroom.show', $course), PHP_URL_PATH));

            // 2. Tempo esgotado.
            //
            //    O contador `[data-quiz-timer]` semeia `data-started-at` do
            //    `started_at` persistido na tentativa `in_progress` aberta
            //    por `StudentQuizController::show()`. Para exercitar o estado
            //    "já expirou" sem dormir um minuto real, empurramos o
            //    `data-started-at` para o passado e reinvocamos
            //    `window.QuizTimer.bind()` — mesmo caminho de código de uma
            //    expiração real.
            $browser->visit(route('student.quizzes.show', $timedLesson))
                ->waitFor('@quiz-timer')
                ->assertPresent('@quiz-attempt-form');

            $browser->script(
                "var container = document.querySelector('[data-quiz-timer]');"
                .'container.setAttribute("data-started-at", new Date(Date.now() - 999999999).toISOString());'
                .'if (window.QuizTimer && window.QuizTimer.intervalId) { clearInterval(window.QuizTimer.intervalId); }'
                .'window.QuizTimer.bind();'
            );

            $browser->waitForText('Tempo esgotado')
                // Expirar NÃO submete nada por conta própria: a tentativa
                // aberta continua `in_progress`, sem nota.
                ->assertPresent('@quiz-attempt-form');

            $this->assertDatabaseMissing('quiz_attempts', [
                'quiz_id' => $timedQuiz->id,
                'user_id' => $student->id,
                'status' => 'graded',
            ]);
            $this->assertDatabaseHas('quiz_attempts', [
                'quiz_id' => $timedQuiz->id,
                'user_id' => $student->id,
                'status' => 'in_progress',
            ]);

            //    Depois de esgotado o tempo o Aluno ainda submete: o backend
            //    aceita, corrige e força `is_passed = false` mesmo com 100%
            //    de acerto. Envelhecemos o `started_at` persistido da
            //    tentativa aberta (a tela já carregada continua válida) para
            //    exercitar o estouro real, sem esperar o minuto do relógio.
            QuizAttempt::query()
                ->where('quiz_id', $timedQuiz->id)
                ->where('user_id', $student->id)
                ->where('status', 'in_progress')
                ->firstOrFail()
                ->update(['started_at' => now()->subMinutes(10)]);

            $browser->click('@quiz-option-'.$timedCorrectOption->question_id.'-'.$timedCorrectOption->id)
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('não atingiu a nota mínima');

            $this->assertDatabaseHas('quiz_attempts', [
                'quiz_id' => $timedQuiz->id,
                'user_id' => $student->id,
                'status' => 'graded',
                'score_percentage' => 100.00,
                'is_passed' => 0,
            ]);

            // 3. Limite de tentativas atingido, mesmo com repetição permitida.
            $browser->visit(route('student.quizzes.show', $maxAttemptsLesson))
                ->waitFor('@quiz-cannot-attempt')
                ->assertSee('número máximo de tentativas ('.$maxAttemptsQuiz->max_attempts.')')
                ->assertMissing('@quiz-attempt-form');

            // 4. Gabarito exibido quando `show_correct_answers` está ligado.
            $browser->visit(route('student.quizzes.show', $answerKeyLesson))
                ->waitFor('@quiz-answer-key')
                ->assertSee('Gabarito')
                ->assertSeeIn('@answer-key-option-'.$answerKeyCorrectOption->id, '(resposta correta)');

            // 5. Nota abaixo da mínima: a tela de resultado avisa a reprovação.
            $browser->visit(route('student.quizzes.show', $failingLesson))
                ->waitFor('@quiz-attempt-form')
                ->click('@quiz-option-'.$failingWrongOption->question_id.'-'.$failingWrongOption->id)
                ->click('@quiz-attempt-submit')
                ->waitFor('@quiz-attempt-confirm')
                ->click('@quiz-attempt-confirm')
                ->waitForText('não atingiu a nota mínima');

            // 6. Gabarito continua escondido quando `show_correct_answers`
            //    está desligado, mesmo com tentativa corrigida.
            $browser->visit(route('student.quizzes.show', $hiddenKeyLesson))
                ->waitFor('@quiz-attempt-form')
                ->assertMissing('@quiz-answer-key');
        });
    }

    /**
     * Fluxo de exceção do tempo esgotado no navegador: o Aluno abre a prova
     * cronometrada, some sem enviar, e volta depois do prazo. Ao reabrir, a
     * tentativa abandonada é encerrada como reprovada (e passa a contar), a
     * tela avisa isso explicitamente e um novo cronômetro começa do zero —
     * deixar o tempo correr nunca é um jeito barato de ganhar contagem nova.
     */
    public function test_abandoned_timed_attempt_is_expired_on_reopen_and_warns_the_student(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create([
            'time_limit_minutes' => 10,
            'allow_retries' => true,
            'max_attempts' => 3,
        ]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        // A tentativa aberta e abandonada: começou há 45 minutos numa prova
        // de 10, ou seja, o prazo já estourou muito antes da volta do Aluno.
        $abandonedAttempt = QuizAttempt::factory()->for($quiz)->for($student)->inProgress()->create([
            'started_at' => now()->subMinutes(45),
        ]);

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-expired-attempt')
                ->assertVisible('@quiz-expired-attempt')
                ->assertSeeIn('@quiz-expired-attempt', 'O tempo da sua tentativa anterior se esgotou')
                ->assertSeeIn('@quiz-expired-attempt', 'conta no seu total de tentativas')
                // Ainda restam tentativas: a prova reabre com cronômetro novo.
                ->assertPresent('@quiz-attempt-form')
                ->assertVisible('@quiz-timer');
        });

        // A tentativa abandonada virou uma tentativa corrigida, zerada e
        // reprovada — visível para o Aluno, para o Gestor e para as contagens.
        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $abandonedAttempt->id,
            'status' => 'graded',
            'score_percentage' => 0,
            'is_passed' => 0,
        ]);

        // E uma nova tentativa aberta, com a contagem recomeçada agora.
        $freshAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $this->assertNotSame($abandonedAttempt->id, $freshAttempt->id);
        $this->assertTrue($freshAttempt->started_at->greaterThan(now()->subMinutes(5)));
    }
}
