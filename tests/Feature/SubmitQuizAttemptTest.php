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
 * `SubmitQuizAttemptAction`'s correction engine: RN02/RN03
 * exact-set correctness for `single_choice`/`multiple_choice`/
 * `true_false`, RN11's `essay` -> `awaiting_manual_grading` branch, and
 * the lesson-completion handoff to `MarkLessonCompleteAction` on a
 * passing grade.
 */
class SubmitQuizAttemptTest extends TestCase
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
        // count as fully incorrect (RN03), never partial credit.
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
}
