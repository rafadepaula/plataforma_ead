<?php

namespace Tests\Feature;

use App\Actions\SubmitQuizAttemptAction;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * the `professor` role's slice of the manual essay-grading
 * surface (`EssayGradingController::pending/show/grade` + `QuizAttemptPolicy`):
 * the queue narrows to the Courses assigned via the `course_professor` pivot,
 * a professor without the pivot keeps getting 403 from both the grading
 * screen and the grade endpoint, and a successful verdict behaves exactly
 * like the Gestor's (`GradeEssayAnswerAction` → `finalizeGrading()` →
 * `status`/`score_percentage` recalculation). Admin/Gestor keep the whole
 * (org-resolved) queue — no regression. The shared quiz/attempt setup is
 * copied from `EssayGradingTest`/`EssayManualGradingTest` on purpose.
 */
class ProfessorEssayGradingTest extends TestCase
{
    /**
     * @return array{0: Course, 1: Lesson, 2: QuizQuestion, 3: QuizOption, 4: QuizQuestion}
     */
    private function courseWithEssayQuiz(Organization $org): array
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        return [$course, $lesson, $choiceQuestion, $correct, $essayQuestion];
    }

    private function enrolledAluno(Course $course, string $name): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null, 'name' => $name]);
        $aluno->assignRole('aluno');
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return $aluno;
    }

    private function assignProfessor(User $professor, Course $course, User $actor): void
    {
        $course->professors()->attach($professor->id, ['assigned_by' => $actor->id]);
    }

    private function professorFor(Organization $org, string $name = 'Professor Titular'): User
    {
        /** @var User $professor */
        $professor = User::factory()->professor()->create(['org_id' => $org->id, 'name' => $name]);

        return $professor;
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     */
    private function submitAttempt(Lesson $lesson, User $aluno, array $answers): QuizAttempt
    {
        return app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, $answers);
    }

    /**
     * KNOWN APP BUG (not worked around here on purpose): this currently
     * 500s — `EssayGradingController::pending()` feeds a raw
     * `BelongsToMany` into `whereKey()` (`->whereKey($user->taughtCourses()
     * ->select('courses.id'))`), which the grammar compiles as a
     * sub-select and dies with a `TypeError`. The assertion below is the
     * intended contract: a 200 whose queue lists only the assigned
     * Courses' attempts.
     */
    public function test_assigned_professor_sees_only_attempts_from_their_assigned_courses_in_the_queue(): void
    {
        $org = Organization::factory()->create();
        [$courseAssigned, $lessonAssigned, $choiceAssigned, $correctAssigned, $essayAssigned] = $this->courseWithEssayQuiz($org);
        [$courseOther, $lessonOther, $choiceOther, $correctOther, $essayOther] = $this->courseWithEssayQuiz($org);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $courseAssigned, $gestor);

        $alunoAssigned = $this->enrolledAluno($courseAssigned, 'Aluno Atribuido da Silva');
        $alunoOther = $this->enrolledAluno($courseOther, 'Aluno Estranho da Costa');

        $this->submitAttempt($lessonAssigned, $alunoAssigned, [
            ['question_id' => $choiceAssigned->id, 'selected_option_ids' => [$correctAssigned->id]],
            ['question_id' => $essayAssigned->id, 'essay_answer' => 'Resposta do curso atribuído.'],
        ]);
        $this->submitAttempt($lessonOther, $alunoOther, [
            ['question_id' => $choiceOther->id, 'selected_option_ids' => [$correctOther->id]],
            ['question_id' => $essayOther->id, 'essay_answer' => 'Resposta do curso não atribuído.'],
        ]);

        $response = $this->actingAs($professor)->get(route('quiz-attempts.pending'));

        $response->assertOk();
        $response->assertSee('Aluno Atribuido da Silva');
        $response->assertDontSee('Aluno Estranho da Costa');
    }

    public function test_unassigned_professor_gets_403_viewing_the_grading_screen(): void
    {
        $org = Organization::factory()->create();
        [$course, $lesson, $choice, $correct, $essay] = $this->courseWithEssayQuiz($org);

        $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);

        $aluno = $this->enrolledAluno($course, 'Aluno Sem Professor');
        $attempt = $this->submitAttempt($lesson, $aluno, [
            ['question_id' => $choice->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essay->id, 'essay_answer' => 'Resposta dissertativa.'],
        ]);

        $this->actingAs($professor)->get(route('quiz-attempts.show', $attempt))->assertForbidden();
    }

    public function test_unassigned_professor_gets_403_grading_and_no_verdict_is_persisted(): void
    {
        $org = Organization::factory()->create();
        [$course, $lesson, $choice, $correct, $essay] = $this->courseWithEssayQuiz($org);

        $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);

        $aluno = $this->enrolledAluno($course, 'Aluno Sem Professor Dois');
        $attempt = $this->submitAttempt($lesson, $aluno, [
            ['question_id' => $choice->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essay->id, 'essay_answer' => 'Resposta dissertativa.'],
        ]);
        $essayAnswer = $attempt->answers()->where('question_id', $essay->id)->firstOrFail();

        $response = $this->actingAs($professor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => null,
            'graded_by' => null,
        ]);

        $attempt->refresh();
        $this->assertSame('awaiting_manual_grading', $attempt->status);
    }

    public function test_assigned_professor_grades_the_attempt_and_the_score_is_recalculated_like_the_gestors(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();
        // A third question the student leaves entirely unanswered — it
        // still counts in the denominator, scored as wrong.
        QuizQuestion::factory()->for($quiz)->singleChoice()->create();

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $aluno = $this->enrolledAluno($course, 'Aluno Corrigido Nunes');
        $attempt = $this->submitAttempt($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Resposta dissertativa do aluno.'],
        ]);
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        $response = $this->actingAs($professor)->post(route('quiz-attempts.grade', $attempt), [
            'grades' => [
                ['answer_id' => $essayAnswer->id, 'is_correct' => true],
            ],
        ]);

        $response->assertRedirect(route('quiz-attempts.pending'));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        // 3 questions, 2 correct (1 auto + 1 essay), 1 unanswered counted
        // as wrong -> 2/3 = 66.67% (same formula as auto-grading).
        $this->assertEqualsWithDelta(66.67, (float) $attempt->score_percentage, 0.01);
        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('quiz_answers', [
            'id' => $essayAnswer->id,
            'is_correct' => true,
            'graded_by' => $professor->id,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'quiz_passed',
        ]);
    }

    public function test_gestor_still_sees_the_whole_own_org_queue_regardless_of_professor_assignment(): void
    {
        $org = Organization::factory()->create();
        [$courseOne, $lessonOne, $choiceOne, $correctOne, $essayOne] = $this->courseWithEssayQuiz($org);
        [$courseTwo, $lessonTwo, $choiceTwo, $correctTwo, $essayTwo] = $this->courseWithEssayQuiz($org);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $courseOne, $gestor);

        $alunoOne = $this->enrolledAluno($courseOne, 'Aluno Primeiro Rocha');
        $alunoTwo = $this->enrolledAluno($courseTwo, 'Aluno Segundo Moura');

        $this->submitAttempt($lessonOne, $alunoOne, [
            ['question_id' => $choiceOne->id, 'selected_option_ids' => [$correctOne->id]],
            ['question_id' => $essayOne->id, 'essay_answer' => 'Resposta um.'],
        ]);
        $this->submitAttempt($lessonTwo, $alunoTwo, [
            ['question_id' => $choiceTwo->id, 'selected_option_ids' => [$correctTwo->id]],
            ['question_id' => $essayTwo->id, 'essay_answer' => 'Resposta dois.'],
        ]);

        $response = $this->actingAs($gestor)->get(route('quiz-attempts.pending'));

        $response->assertOk();
        $response->assertSee('Aluno Primeiro Rocha');
        $response->assertSee('Aluno Segundo Moura');
    }
}
