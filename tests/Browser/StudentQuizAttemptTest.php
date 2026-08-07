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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-08 RF09 — E2E coverage of the Aluno's single-page quiz-taking
 * screen: opening it from the classroom's quiz-lesson placeholder,
 * answering every question in one submission, and seeing the resulting
 * grade/lesson-completion feedback.
 */
class StudentQuizAttemptTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_student_takes_a_fully_auto_graded_quiz_from_the_classroom_and_passes(): void
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

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
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

    public function test_a_quiz_with_an_essay_question_leaves_the_student_awaiting_manual_grading(): void
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
    }

    public function test_a_student_who_exhausted_their_attempts_sees_the_form_hidden_not_a_500(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['allow_retries' => false]);
        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create();

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-cannot-attempt')
                ->assertSee('não permite novas tentativas')
                ->assertMissing('@quiz-attempt-form');
        });
    }

    public function test_the_answer_key_is_shown_when_show_correct_answers_is_enabled(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['show_correct_answers' => true]);

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a capital do Brasil?',
        ]);
        $correctOption = QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Brasília']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'São Paulo']);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create();

        $this->browse(function (Browser $browser) use ($student, $lesson, $correctOption): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $lesson))
                ->waitFor('@quiz-answer-key')
                ->assertSee('Gabarito')
                ->assertSeeIn('@answer-key-option-'.$correctOption->id, '(resposta correta)');
        });
    }
}
