<?php

namespace Tests\Unit\Services;

use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\CourseCompletionEvaluator;
use Tests\TestCase;

/**
 * `CourseCompletionEvaluator` is the single "is this course concluded for
 * this student?" verdict shared by enrollment completion
 * (`EvaluateCourseCompletionAction`) and certificate issuance
 * (`IssueCertificateAction`): literally the registered
 * `course_completion_rules` rows, ANDed — `all_lessons` is one
 * parametrized rule among them, never a mandatory gate.
 */
class CourseCompletionEvaluatorTest extends TestCase
{
    private function enrolledStudent(Course $course, int $progressPercentage = 0): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => $progressPercentage,
        ]);

        return $student;
    }

    private function courseWithPublishedLesson(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['is_published' => true]);

        return [$course, $module, $lesson];
    }

    public function test_a_course_with_no_rules_is_never_satisfied(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $student = $this->enrolledStudent($course, 100);

        $this->assertFalse(app(CourseCompletionEvaluator::class)->satisfied($course, $student));
    }

    public function test_all_lessons_rule_reads_the_persisted_progress_percentage(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $below = $this->enrolledStudent($course, 79);
        $above = $this->enrolledStudent($course, 80);

        CourseCompletionRule::factory()->for($course)->allLessons(80)->create();

        $evaluator = app(CourseCompletionEvaluator::class);

        $this->assertFalse($evaluator->satisfied($course, $below));
        $this->assertTrue($evaluator->satisfied($course, $above));
    }

    public function test_progress_override_wins_over_the_stale_persisted_percentage(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $student = $this->enrolledStudent($course, 10);

        $rule = CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $evaluator = app(CourseCompletionEvaluator::class);

        // Fresh recompute (what `EvaluateCourseCompletionAction` passes)
        // satisfies the rule even though the pivot still holds 10%.
        $this->assertTrue($evaluator->ruleSatisfied($rule, $course, $student, 100));
        $this->assertFalse($evaluator->ruleSatisfied($rule, $course, $student, 99));
        $this->assertFalse($evaluator->ruleSatisfied($rule, $course, $student));
    }

    public function test_min_quiz_score_rule_uses_the_best_graded_attempt(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $quizLesson = Lesson::factory()->for($course->modules()->first())->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($quizLesson)->create();
        $student = $this->enrolledStudent($course);

        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        $evaluator = app(CourseCompletionEvaluator::class);

        // No graded attempt yet.
        $this->assertFalse($evaluator->satisfied($course, $student));

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 60]);
        $this->assertFalse($evaluator->satisfied($course, $student));

        // Best score wins over the earlier 60.
        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 90]);
        $this->assertTrue($evaluator->satisfied($course, $student));
    }

    public function test_min_quiz_score_rule_with_a_dangling_target_is_not_satisfied(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $student = $this->enrolledStudent($course);

        CourseCompletionRule::factory()->for($course)->minQuizScore(999999, 1)->create();

        $this->assertFalse(app(CourseCompletionEvaluator::class)->satisfied($course, $student));
    }

    public function test_specific_module_rule_requires_every_lesson_of_the_module_completed(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $targetModule = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($targetModule)->create(['is_published' => true]);
        // A lesson outside the target module must not count either way.
        Lesson::factory()->for(Module::factory()->for($course)->create())->create(['is_published' => true]);

        $student = $this->enrolledStudent($course);

        CourseCompletionRule::factory()->for($course)->specificModule($targetModule->id)->create();

        $evaluator = app(CourseCompletionEvaluator::class);

        $this->assertFalse($evaluator->satisfied($course, $student));

        $lessons->first()->progress()->create([
            'user_id' => $student->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);
        $this->assertFalse($evaluator->satisfied($course, $student));

        $lessons->last()->progress()->create([
            'user_id' => $student->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);
        $this->assertTrue($evaluator->satisfied($course, $student));
    }

    public function test_specific_module_rule_with_no_lessons_or_a_dangling_target_is_not_satisfied(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $emptyModule = Module::factory()->for($course)->create();
        $student = $this->enrolledStudent($course);

        CourseCompletionRule::factory()->for($course)->specificModule($emptyModule->id)->create();

        $this->assertFalse(app(CourseCompletionEvaluator::class)->satisfied($course, $student));

        $emptyModule->delete();

        $this->assertFalse(app(CourseCompletionEvaluator::class)->satisfied($course, $student));
    }

    public function test_multiple_rules_are_anded_a_single_failure_fails_the_whole_course(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $quizLesson = Lesson::factory()->for($course->modules()->first())->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($quizLesson)->create();
        $student = $this->enrolledStudent($course, 100);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();
        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        $evaluator = app(CourseCompletionEvaluator::class);

        // `all_lessons` passes, `min_quiz_score` still unattempted.
        $this->assertFalse($evaluator->satisfied($course, $student));

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 95]);

        $this->assertTrue($evaluator->satisfied($course, $student));
    }

    public function test_an_unknown_rule_type_is_never_satisfied(): void
    {
        [$course] = $this->courseWithPublishedLesson();
        $student = $this->enrolledStudent($course, 100);

        // The DB CHECK constraint rejects unknown types on write, so the
        // defensive `default => false` branch is exercised with a
        // never-persisted instance — `ruleSatisfied` reaches `default`
        // before touching the database.
        $rule = new CourseCompletionRule([
            'course_id' => $course->id,
            'rule_type' => 'no_longer_supported',
            'required_percentage' => 0,
        ]);

        $this->assertFalse(app(CourseCompletionEvaluator::class)->ruleSatisfied($rule, $course, $student));
    }
}
