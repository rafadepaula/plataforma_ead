<?php

namespace Tests\Feature;

use App\Actions\IssueCertificateAction;
use App\Enums\Permissions\RolesEnum;
use App\Events\CourseCompletedByStudent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Tests\TestCase;

/**
 * `IssueCertificateAction` evaluates every
 * `course_completion_rules` row for the Course (AND logic across all 3
 * `rule_type`s) and idempotently issues a `Certificate` respecting the
 * `UNIQUE(user_id, course_id)` constraint.
 */
class CertificateEligibilityTest extends TestCase
{
    private function studentEnrolledIn(Course $course, int $progressPercentage = 100): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'completed',
            'progress_percentage' => $progressPercentage,
        ]);

        return $student;
    }

    public function test_all_lessons_rule_issues_a_certificate_when_progress_meets_the_threshold(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNotNull($certificate);
        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_all_lessons_rule_does_not_issue_when_progress_is_below_the_threshold(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 80);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_min_quiz_score_rule_issues_when_the_students_best_graded_attempt_meets_the_threshold(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $student = $this->studentEnrolledIn($course);

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 90]);

        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNotNull($certificate);
    }

    public function test_min_quiz_score_rule_does_not_issue_when_the_best_score_is_below_the_threshold(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $student = $this->studentEnrolledIn($course);

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 60]);

        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_min_quiz_score_rule_does_not_issue_when_the_student_has_no_graded_attempt(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $student = $this->studentEnrolledIn($course);

        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_specific_module_rule_issues_when_every_lesson_of_the_target_module_is_completed(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $targetModule = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($targetModule)->create(['is_published' => true]);
        $student = $this->studentEnrolledIn($course);

        foreach ($lessons as $lesson) {
            $lesson->progress()->create([
                'user_id' => $student->id,
                'is_completed' => true,
                'completion_source' => 'manual_click',
                'completed_at' => now(),
            ]);
        }

        CourseCompletionRule::factory()->for($course)->specificModule($targetModule->id)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNotNull($certificate);
    }

    public function test_specific_module_rule_does_not_issue_when_a_lesson_of_the_target_module_is_incomplete(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $targetModule = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($targetModule)->create(['is_published' => true]);
        $student = $this->studentEnrolledIn($course);

        $lessons->first()->progress()->create([
            'user_id' => $student->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        CourseCompletionRule::factory()->for($course)->specificModule($targetModule->id)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_multiple_rules_require_all_to_pass_and_logic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $student = $this->studentEnrolledIn($course, 100);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();
        CourseCompletionRule::factory()->for($course)->minQuizScore($quiz->id, 80)->create();

        // Only the `all_lessons` rule is satisfied so far — no graded
        // attempt exists yet for the `min_quiz_score` rule's target quiz.
        $this->assertNull(app(IssueCertificateAction::class)->execute($course, $student));

        QuizAttempt::factory()->for($quiz)->for($student)->graded()->create(['score_percentage' => 95]);

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNotNull($certificate);
    }

    public function test_course_with_no_completion_rules_never_issues_a_certificate(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_issuing_is_idempotent_and_does_not_duplicate_an_existing_certificate(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $first = app(IssueCertificateAction::class)->execute($course, $student);
        $second = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_a_revoked_certificate_is_never_reissued_for_the_same_pair(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        $revoked = Certificate::factory()->for($course)->for($student)->revoked()->create();

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertDatabaseCount('certificates', 1);
        $this->assertNotNull($revoked->fresh()->revoked_at);
    }

    public function test_min_quiz_score_rule_treats_a_target_id_that_no_longer_resolves_as_not_satisfied(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        // `target_id` deliberately points at a `quizzes.id` that does not
        // exist (no DB foreign key by design — see the migration comment),
        // simulating a deleted-out-from-under Quiz.
        CourseCompletionRule::factory()->for($course)->minQuizScore(999999, 80)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_specific_module_rule_treats_a_target_id_that_no_longer_resolves_as_not_satisfied(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        // `target_id` deliberately points at a `modules.id` that does not
        // exist, simulating a deleted-out-from-under Module.
        CourseCompletionRule::factory()->for($course)->specificModule(999999)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_specific_module_rule_does_not_issue_when_the_target_module_has_no_lessons(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $emptyModule = Module::factory()->for($course)->create();
        $student = $this->studentEnrolledIn($course, 100);

        CourseCompletionRule::factory()->for($course)->specificModule($emptyModule->id)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNull($certificate);
    }

    public function test_the_auto_discovered_listener_issues_a_certificate_when_course_completed_by_student_fires(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentEnrolledIn($course, 100);

        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        CourseCompletedByStudent::dispatch($course, $student);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }
}
