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
 * SPEC-08 RF09 — E2E coverage of the Aluno's single-page quiz-taking
 * screen: opening it from the classroom's quiz-lesson placeholder,
 * answering every question in one submission, and seeing the resulting
 * grade/lesson-completion feedback.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada auto-corrigida (abrir da sala → responder → aprovar → lição
 * concluída), a jornada dissertativa (aguardando correção manual) e os
 * estados de bloqueio da tela (tentativas esgotadas, tempo esgotado,
 * gabarito) — cada uma num único método, sempre com o mesmo Aluno.
 */
class StudentQuizAttemptTest extends DuskTestCase
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
     * Os três estados de bloqueio/feedback da tela do Aluno, percorridos
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

        // (a) Quiz sem novas tentativas, já com uma tentativa corrigida.
        $noRetryLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $noRetryQuiz = Quiz::factory()->for($noRetryLesson)->create(['allow_retries' => false]);
        QuizQuestion::factory()->for($noRetryQuiz)->singleChoice()->create();
        QuizAttempt::factory()->for($noRetryQuiz)->for($student)->graded()->create();

        // (b) Quiz com limite de tempo (o contador é cosmético — ver abaixo).
        $timedLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $timedQuiz = Quiz::factory()->for($timedLesson)->create(['time_limit_minutes' => 1]);
        $timedQuestion = QuizQuestion::factory()->for($timedQuiz)->singleChoice()->create();
        QuizOption::factory()->for($timedQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($timedQuestion, 'question')->incorrect()->create();

        // (c) Quiz com gabarito liberado após a tentativa corrigida.
        $answerKeyLesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $answerKeyQuiz = Quiz::factory()->for($answerKeyLesson)->create(['show_correct_answers' => true]);
        $answerKeyQuestion = QuizQuestion::factory()->for($answerKeyQuiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        $answerKeyCorrectOption = QuizOption::factory()->for($answerKeyQuestion, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($answerKeyQuestion, 'question')->incorrect()->create(['option_text' => 'São Paulo']);
        QuizAttempt::factory()->for($answerKeyQuiz)->for($student)->graded()->create();

        $this->browse(function (Browser $browser) use (
            $student,
            $noRetryLesson,
            $timedLesson,
            $timedQuiz,
            $answerKeyLesson,
            $answerKeyCorrectOption
        ): void {
            // 1. Tentativas esgotadas: formulário some, sem 500.
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $noRetryLesson))
                ->waitFor('@quiz-cannot-attempt')
                ->assertSee('não permite novas tentativas')
                ->assertMissing('@quiz-attempt-form');

            // 2. Tempo esgotado.
            //
            //    O contador `[data-quiz-timer]` semeia `data-started-at` do
            //    próprio render (ver `quizzes-conventions`:
            //    `StudentQuizController::show()` não cria `QuizAttempt` na
            //    entrada, então não há `started_at` persistido). Para exercitar
            //    o estado "já expirou" sem dormir um minuto real, empurramos o
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
                // Expirar NÃO submete nada por conta própria.
                ->assertPresent('@quiz-attempt-form');

            $this->assertDatabaseMissing('quiz_attempts', [
                'quiz_id' => $timedQuiz->id,
                'user_id' => $student->id,
            ]);

            // 3. Gabarito exibido quando `show_correct_answers` está ligado.
            $browser->visit(route('student.quizzes.show', $answerKeyLesson))
                ->waitFor('@quiz-answer-key')
                ->assertSee('Gabarito')
                ->assertSeeIn('@answer-key-option-'.$answerKeyCorrectOption->id, '(resposta correta)');
        });
    }
}
