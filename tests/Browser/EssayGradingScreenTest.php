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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-08 §2.1 — E2E coverage of the Gestor's manual essay-grading screen:
 * the pending-attempts queue and grading a `type=essay` answer, which
 * finalizes the attempt (`status = graded`) once every essay answer has a
 * verdict.
 */
class EssayGradingScreenTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_grades_a_pending_essay_answer_from_the_queue(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()->create();
        $answer = QuizAnswer::factory()->for($attempt, 'attempt')->for($essayQuestion, 'question')
            ->essay('Minha resposta dissertativa.')->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor, $attempt, $answer): void {
            $browser->loginAs($gestor)
                ->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempt-row-'.$attempt->id)
                ->click('@grade-attempt-'.$attempt->id)
                ->waitFor('@grade-attempt-form')
                ->assertSeeIn('@essay-answer-'.$answer->question_id, 'Minha resposta dissertativa.')
                ->click('@grade-correct-'.$answer->id)
                ->click('@grade-attempt-submit')
                ->waitForText('Correções Pendentes')
                ->assertDontSee('Corrigir');
        });

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $answer->id,
            'is_correct' => true,
            'graded_by' => $gestor->id,
        ]);
    }

    public function test_the_pending_queue_is_empty_once_there_is_nothing_left_to_grade(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempts-empty')
                ->assertSee('Nenhuma correção pendente.');
        });
    }

    public function test_gestor_can_create_a_quiz_via_the_ui(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $title = 'Quiz de Avaliação Final';
        $minScore = '70';

        $this->browse(function (Browser $browser) use ($gestor, $lesson, $title, $minScore): void {
            $browser->loginAs($gestor)
                ->visit(route('quizzes.create', $lesson))
                ->waitFor('@quiz-form')
                ->type('@quiz-title-input', $title)
                ->clear('@quiz-min-score')
                ->type('@quiz-min-score', $minScore)
                ->press('@quiz-submit')
                ->waitForText('Questionário criado com sucesso.');
        });

        $this->assertDatabaseHas('quizzes', [
            'lesson_id' => $lesson->id,
            'title' => $title,
            'min_score_percentage' => $minScore,
        ]);
    }

    public function test_a_single_choice_question_without_a_correct_option_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor, $quiz): void {
            $browser->loginAs($gestor)
                ->visit(route('quizzes.edit', $quiz))
                ->click('@new-question')
                ->waitFor('@question-form-create')
                ->type('@question-text-create', 'Questão sem opção correta marcada.')
                ->select('@question-type-create', 'single_choice')
                ->type('@option-text-create-0', 'Opção A')
                ->type('@option-text-create-1', 'Opção B')
                ->press('@question-submit-create')
                ->waitForText('Questões de escolha única devem ter exatamente 1 opção correta.');
        });

        $this->assertDatabaseCount('quiz_questions', 0);
    }
}
