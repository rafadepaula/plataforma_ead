<?php

namespace Tests\Browser;

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
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-08 §2.1 — E2E coverage do fluxo do Gestor sobre questionários: fila
 * de correções vazia, criação de quiz pela UI, rejeição de questão de
 * escolha única sem gabarito, e correção manual de uma resposta
 * dissertativa (que finaliza a tentativa em `status = graded`).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): é o
 * mesmo Gestor percorrendo a jornada inteira de autoria + correção numa
 * única sessão de navegador.
 */
class EssayGradingScreenTest extends DuskTestCase
{
    public function test_gestor_quiz_authoring_and_essay_grading_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($gestor, $lesson, $aluno): void {
            // 1. Sem tentativas pendentes, a fila mostra o estado vazio.
            $browser->loginAs($gestor)
                ->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempts-empty')
                ->assertSee('Nenhuma correção pendente.');

            // 2. Criação do questionário pela UI.
            $browser->visit(route('quizzes.create', $lesson))
                ->waitFor('@quiz-form')
                ->type('@quiz-title-input', 'Quiz de Avaliação Final')
                ->clear('@quiz-min-score')
                ->type('@quiz-min-score', '70')
                ->press('@quiz-submit')
                ->waitForText('Questionário criado com sucesso.');

            $this->assertDatabaseHas('quizzes', [
                'lesson_id' => $lesson->id,
                'title' => 'Quiz de Avaliação Final',
                'min_score_percentage' => '70',
            ]);

            $quiz = Quiz::query()->where('lesson_id', $lesson->id)->firstOrFail();

            // 3. Questão de escolha única sem opção correta é rejeitada.
            $browser->visit(route('quizzes.edit', $quiz))
                ->click('@new-question')
                ->waitFor('@question-form-create')
                ->type('@question-text-create', 'Questão sem opção correta marcada.')
                ->select('@question-type-create', 'single_choice')
                ->type('@option-text-create-0', 'Opção A')
                ->type('@option-text-create-1', 'Opção B')
                ->press('@question-submit-create')
                ->waitForText('Questões de escolha única devem ter exatamente 1 opção correta.');

            $this->assertDatabaseCount('quiz_questions', 0);

            // 4. Com uma tentativa dissertativa aguardando correção, a fila
            //    passa a listá-la e o Gestor corrige, finalizando a tentativa.
            $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();
            $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()->create();
            $answer = QuizAnswer::factory()->for($attempt, 'attempt')->for($essayQuestion, 'question')
                ->essay('Minha resposta dissertativa.')->create();

            $browser->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempt-row-'.$attempt->id)
                ->click('@grade-attempt-'.$attempt->id)
                ->waitFor('@grade-attempt-form')
                ->assertSeeIn('@essay-answer-'.$answer->question_id, 'Minha resposta dissertativa.')
                ->click('@grade-correct-'.$answer->id)
                ->click('@grade-attempt-submit')
                ->waitForText('Correções Pendentes')
                ->assertDontSee('Corrigir');

            $attempt->refresh();
            $this->assertSame('graded', $attempt->status);
            $this->assertDatabaseHas('quiz_answers', [
                'id' => $answer->id,
                'is_correct' => true,
                'graded_by' => $gestor->id,
            ]);
        });
    }
}
