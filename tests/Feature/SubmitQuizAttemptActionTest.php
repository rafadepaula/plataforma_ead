<?php

namespace Tests\Feature;

use App\Actions\SubmitQuizAttemptAction;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * `SubmitQuizAttemptAction`'s correction engine:
 * exact-set correctness for `single_choice`/`multiple_choice`/
 * `true_false`,  `essay` -> `awaiting_manual_grading` branch, and
 * the lesson-completion handoff to `MarkLessonCompleteAction` on a
 * passing grade.
 */
class SubmitQuizAttemptActionTest extends TestCase
{
    private function enrolledAlunoAndQuiz(array $quizAttributes = []): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create($quizAttributes);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return [$aluno, $lesson, $quiz];
    }

    private function singleChoiceQuestion(Quiz $quiz): array
    {
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        return [$question, $correct];
    }

    public function test_a_fully_correct_single_choice_quiz_is_graded_and_passes(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz(['min_score_percentage' => 70]);
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
        ]);

        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->score_percentage);
        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'quiz_passed',
        ]);
    }

    public function test_multiple_choice_requires_the_exact_correct_set_no_partial_credit(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz(['min_score_percentage' => 70]);

        $question = QuizQuestion::factory()->for($quiz)->multipleChoice()->create();
        $correctOne = QuizOption::factory()->for($question, 'question')->correct()->create();
        $correctTwo = QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        // Only 1 of the 2 correct options selected — partial match must
        // count as fully incorrect , never partial credit.
        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOne->id]],
        ]);

        $this->assertSame(0.0, (float) $attempt->score_percentage);
        $this->assertFalse($attempt->is_passed);

        $secondAluno = User::factory()->create(['org_id' => null]);
        $secondAluno->assignRole(RolesEnum::ALUNO->value);
        $secondAluno->courses()->attach($lesson->module->course_id, ['status' => 'active', 'enrolled_at' => now()]);

        $secondAttempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $secondAluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOne->id, $correctTwo->id]],
        ]);

        $this->assertSame(100.0, (float) $secondAttempt->score_percentage);
        $this->assertTrue($secondAttempt->is_passed);
    }

    public function test_an_empty_selected_option_ids_is_always_incorrect(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz();
        [$question] = $this->singleChoiceQuestion($quiz);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => []],
        ]);

        $this->assertSame(0.0, (float) $attempt->score_percentage);
        $this->assertFalse($attempt->is_passed);
    }

    public function test_an_unanswered_question_is_scored_as_incorrect_not_excluded(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz();
        [$question] = $this->singleChoiceQuestion($quiz);
        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => []],
        ]);

        $this->assertSame(0.0, (float) $attempt->score_percentage);
    }

    public function test_a_quiz_with_an_essay_question_is_left_awaiting_manual_grading_and_does_not_complete_the_lesson(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz();
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Minha resposta dissertativa.'],
        ]);

        $this->assertSame('awaiting_manual_grading', $attempt->status);
        $this->assertNull($attempt->score_percentage);
        $this->assertNull($attempt->is_passed);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essayQuestion->id,
            'essay_answer' => 'Minha resposta dissertativa.',
            'is_correct' => null,
        ]);
    }

    public function test_an_attempt_submitted_after_the_time_limit_is_graded_but_never_passes(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);

        // The student opened the page 30 minutes ago on a quiz limited to
        // 10 minutes: the submission is still accepted and graded, only
        // `is_passed` is forced to false.
        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subMinutes(30),
        ]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
        ]);

        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->score_percentage);
        $this->assertFalse($attempt->is_passed);
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * The open attempt is resumed instead of a fresh one being opened, so
     * the countdown cannot be restarted by reloading the quiz page.
     */
    public function test_an_open_attempt_is_resumed_so_its_start_time_survives_a_page_reload(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);

        $openAttempt = QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create([
            'started_at' => now()->subMinutes(45),
        ]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
        ]);

        $this->assertSame($openAttempt->id, $attempt->id);
        $this->assertSame(1, QuizAttempt::query()->count());
        $this->assertFalse($attempt->is_passed);
    }

    /**
     * Sem tentativa aberta (quiz sem cronômetro, enviado sem passar pela
     * tela), a tentativa nasce no envio: `started_at` é carimbado com o
     * relógio do servidor naquele instante, e não fica nulo nem no passado.
     */
    public function test_without_an_open_attempt_the_submit_time_becomes_the_attempt_start(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
        ]);

        $this->assertSame('graded', $attempt->status);
        $this->assertTrue($attempt->is_passed);
        $this->assertNotNull($attempt->started_at);
        $this->assertTrue(
            $attempt->started_at->greaterThan(now()->subMinute()),
            'A tentativa aberta no envio deve começar a contar agora.'
        );
        $this->assertTrue($attempt->started_at->lessThanOrEqualTo(now()));
    }

    public function test_a_student_without_an_active_enrollment_cannot_submit(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        [$question, $correctOption] = $this->singleChoiceQuestion($quiz);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        try {
            app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
                ['question_id' => $question->id, 'selected_option_ids' => [$correctOption->id]],
            ]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quiz', $e->errors());
        }

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_a_quiz_without_questions_is_graded_zero_and_still_completes_the_lesson_when_no_minimum_score(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz(['min_score_percentage' => 0]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, []);

        $this->assertSame('graded', $attempt->status);
        $this->assertSame(0.0, (float) $attempt->score_percentage);
        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_an_option_belonging_to_another_quiz_is_scored_as_incorrect(): void
    {
        [$aluno, $lesson, $quiz] = $this->enrolledAlunoAndQuiz(['min_score_percentage' => 70]);
        [$question] = $this->singleChoiceQuestion($quiz);

        [, $foreignLesson, $foreignQuiz] = $this->enrolledAlunoAndQuiz();
        [, $foreignCorrectOption] = $this->singleChoiceQuestion($foreignQuiz);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$foreignCorrectOption->id]],
        ]);

        $this->assertSame(0.0, (float) $attempt->score_percentage);
        $this->assertFalse($attempt->is_passed);
    }
}
