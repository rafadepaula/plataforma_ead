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
 * E2E coverage do fluxo do Gestor sobre questionários: fila
 * de correções vazia, criação de quiz pela UI, rejeição de questão de
 * escolha única sem gabarito, e correção manual de respostas
 * dissertativas — incluindo fila FIFO com múltiplas tentativas, leitura
 * da resposta (com o fallback de resposta vazia), progresso ao vivo da
 * correção, o guard de submit com pendência e a finalização que remove a
 * tentativa da fila (`status = graded`).
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

        $alunoMaisNovo = User::factory()->create(['org_id' => null]);
        $alunoMaisNovo->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($gestor, $lesson, $aluno, $alunoMaisNovo): void {
            // 1. Sem tentativas pendentes, a fila mostra o estado vazio.
            $browser->loginAs($gestor)
                ->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempts-empty')
                ->assertSee('Nenhuma correção pendente');

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

            // 4. Duas tentativas dissertativas aguardando correção: a de
            //    `$aluno` foi concluída antes da de `$alunoMaisNovo`, então
            //    a fila FIFO deve listar `$aluno` primeiro.
            $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();
            $essayQuestion2 = QuizQuestion::factory()->for($quiz)->essay()->create();

            $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()
                ->create(['completed_at' => now()->subMinutes(10)]);
            $answer = QuizAnswer::factory()->for($attempt, 'attempt')->for($essayQuestion, 'question')
                ->essay('Minha resposta dissertativa completa.')->create();
            // Resposta vazia (string '', não null) — deve cair no fallback
            // "O aluno não respondeu esta questão.".
            $answerEmpty = QuizAnswer::factory()->for($attempt, 'attempt')->for($essayQuestion2, 'question')
                ->essay('')->create();

            $attemptNovo = QuizAttempt::factory()->for($quiz)->for($alunoMaisNovo)->awaitingManualGrading()
                ->create(['completed_at' => now()->subMinutes(2)]);
            QuizAnswer::factory()->for($attemptNovo, 'attempt')->for($essayQuestion, 'question')->essay()->create();
            QuizAnswer::factory()->for($attemptNovo, 'attempt')->for($essayQuestion2, 'question')->essay()->create();

            $browser->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempt-row-'.$attempt->id)
                ->assertPresent('@pending-attempt-row-'.$attemptNovo->id);

            $rowOrder = $browser->script(
                "return Array.from(document.querySelectorAll('tbody tr[dusk]')).map(el => el.getAttribute('dusk'))"
            )[0];
            $this->assertSame(
                ['pending-attempt-row-'.$attempt->id, 'pending-attempt-row-'.$attemptNovo->id],
                $rowOrder,
                'A fila deve listar a tentativa mais antiga (FIFO) primeiro.'
            );

            // 5. Abertura da tentativa mais antiga: o texto de cada resposta
            //    dissertativa aparece, inclusive o fallback da vazia.
            $browser->click('@grade-attempt-'.$attempt->id)
                ->waitFor('@grade-attempt-form')
                ->assertSeeIn('@essay-answer-'.$answer->question_id, 'Minha resposta dissertativa completa.')
                ->assertSeeIn('@essay-answer-'.$answerEmpty->question_id, 'O aluno não respondeu esta questão.');

            // 6. Progresso ao vivo: marcar 1 de 2 vereditos atualiza o texto
            //    "X de Y vereditos" e mantém o chip "Pronto para salvar" oculto.
            $browser->assertSeeIn('[data-grading-progress-label]', '0 de 2 vereditos')
                ->click('@grade-correct-'.$answer->id)
                ->waitForTextIn('[data-grading-progress-label]', '1 de 2 vereditos');

            $this->assertTrue(
                $browser->script("return document.querySelector('[data-grading-ready-chip]').classList.contains('d-none')")[0],
                'O chip "Pronto para salvar" não deve aparecer antes de todos os vereditos definidos.'
            );

            // 7. Tentar salvar com um veredito pendente é bloqueado: alerta
            //    aparece, foco vai para o primeiro rádio pendente e o
            //    radiogroup da questão pendente recebe o contorno crítico.
            $browser->click('@grade-attempt-submit');

            $this->assertFalse(
                $browser->script("return document.querySelector('[data-grading-alert]').classList.contains('d-none')")[0],
                'O alerta de vereditos pendentes deveria ficar visível.'
            );

            $pendingRadioName = 'grades[1][is_correct]';
            $focusedName = $browser->script('return document.activeElement.getAttribute("name")')[0];
            $this->assertSame($pendingRadioName, $focusedName, 'O foco deveria ir para o rádio da questão pendente.');

            $hasCriticalOutline = $browser->script(
                "return document.querySelector('[dusk=\"grade-correct-{$answerEmpty->id}\"]').closest('[data-verdict-question]').querySelector('.ds-verdict').classList.contains('has-error')"
            )[0];
            $this->assertTrue($hasCriticalOutline, 'O radiogroup da questão pendente deveria ganhar o contorno crítico.');

            $attempt->refresh();
            $this->assertSame('awaiting_manual_grading', $attempt->status, 'O guard do JS não deve ter permitido o submit real.');

            // 8. Completar o veredito pendente limpa o alerta/contorno,
            //    conclui o progresso e libera o chip "Pronto para salvar".
            $browser->click('@grade-incorrect-'.$answerEmpty->id)
                ->waitForTextIn('[data-grading-progress-label]', '2 de 2 vereditos');

            $this->assertFalse(
                $browser->script("return document.querySelector('[data-grading-ready-chip]').classList.contains('d-none')")[0],
                'O chip "Pronto para salvar" deveria aparecer com 100% dos vereditos definidos.'
            );
            $this->assertTrue(
                $browser->script("return document.querySelector('[data-grading-alert]').classList.contains('d-none')")[0],
                'O alerta deveria ser escondido assim que a última questão pendente for resolvida.'
            );

            // 9. Submit bem-sucedido: volta para a fila, com a tentativa
            //    corrigida removida e a mais nova ainda pendente.
            $browser->click('@grade-attempt-submit')
                ->waitForText('Correções Pendentes')
                ->assertMissing('@pending-attempt-row-'.$attempt->id)
                ->assertPresent('@pending-attempt-row-'.$attemptNovo->id);

            $attempt->refresh();
            $this->assertSame('graded', $attempt->status);
            $this->assertDatabaseHas('quiz_answers', [
                'id' => $answer->id,
                'is_correct' => true,
                'graded_by' => $gestor->id,
            ]);
            $this->assertDatabaseHas('quiz_answers', [
                'id' => $answerEmpty->id,
                'is_correct' => false,
                'graded_by' => $gestor->id,
            ]);
        });
    }
}
