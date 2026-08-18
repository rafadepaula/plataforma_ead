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
 * `allow_retries`/`max_attempts`/`time_limit_minutes`
 * enforcement inside `SubmitQuizAttemptAction`.
 */
class QuizAttemptLimitsTest extends TestCase
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

        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        return [$aluno, $lesson, $quiz, $question, $correct];
    }

    private function submit(Lesson $lesson, User $aluno, QuizQuestion $question, QuizOption $option): QuizAttempt
    {
        return app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $question->id, 'selected_option_ids' => [$option->id]],
        ]);
    }

    public function test_a_second_submission_is_blocked_when_allow_retries_is_false(): void
    {
        [$aluno, $lesson, , $question, $correct] = $this->enrolledAlunoAndQuiz(['allow_retries' => false]);

        $this->submit($lesson, $aluno, $question, $correct);

        $this->expectException(ValidationException::class);

        $this->submit($lesson, $aluno, $question, $correct);
    }

    public function test_a_retry_is_allowed_when_allow_retries_is_true_and_no_max_attempts_set(): void
    {
        [$aluno, $lesson, , $question, $correct] = $this->enrolledAlunoAndQuiz(['allow_retries' => true, 'max_attempts' => null]);

        $this->submit($lesson, $aluno, $question, $correct);
        $second = $this->submit($lesson, $aluno, $question, $correct);

        $this->assertSame('graded', $second->status);
        $this->assertSame(2, QuizAttempt::query()->count());
    }

    public function test_submission_is_blocked_once_max_attempts_is_reached_even_with_allow_retries_true(): void
    {
        [$aluno, $lesson, , $question, $correct] = $this->enrolledAlunoAndQuiz(['allow_retries' => true, 'max_attempts' => 2]);

        $this->submit($lesson, $aluno, $question, $correct);
        $this->submit($lesson, $aluno, $question, $correct);

        $this->expectException(ValidationException::class);

        $this->submit($lesson, $aluno, $question, $correct);
    }

    public function test_max_attempts_counts_only_completed_submissions_not_an_abandoned_in_progress_attempt(): void
    {
        [$aluno, $lesson, $quiz, $question, $correct] = $this->enrolledAlunoAndQuiz(['allow_retries' => true, 'max_attempts' => 1]);

        // An abandoned in_progress attempt (e.g. a stale row from a
        // different flow) must never count toward the max_attempts limit.
        QuizAttempt::factory()->for($quiz)->for($aluno)->inProgress()->create();

        $attempt = $this->submit($lesson, $aluno, $question, $correct);

        $this->assertSame('graded', $attempt->status);
    }

    public function test_a_within_time_limit_submission_passes_normally(): void
    {
        [$aluno, $lesson, , $question, $correct] = $this->enrolledAlunoAndQuiz([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);

        $attempt = $this->submit($lesson, $aluno, $question, $correct);

        $this->assertSame('graded', $attempt->status);
        $this->assertTrue($attempt->is_passed);
    }

    public function test_a_100_percent_attempt_submitted_over_the_time_limit_is_graded_but_forced_to_not_passed(): void
    {
        [, , $quiz, $question, $correct] = $this->enrolledAlunoAndQuiz([
            'time_limit_minutes' => 10,
            'min_score_percentage' => 0,
        ]);

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        // Crafted directly (bypassing the Action's own started_at
        // stamping) so `completed_at - started_at` exceeds
        // `time_limit_minutes`, exercising `finalizeGrading()`'s
        // computed-on-read time check ( §1.3 — an over-limit
        // submission is accepted, only `is_passed` is forced to `false`).
        $attempt = QuizAttempt::factory()
            ->for($quiz)
            ->for($aluno)
            ->awaitingManualGrading()
            ->create([
                'started_at' => now()->subMinutes(30),
                'completed_at' => now()->subMinutes(15),
            ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$correct->id],
            'is_correct' => true,
        ]);

        $finalized = app(SubmitQuizAttemptAction::class)->finalizeGrading($attempt);

        $this->assertSame('graded', $finalized->status);
        $this->assertEquals(100.0, (float) $finalized->score_percentage);
        $this->assertFalse($finalized->is_passed);
    }
}
